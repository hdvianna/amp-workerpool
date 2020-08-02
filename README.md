# Parallel Worker Pool

Parallel worker pool uses the [PHP parallel extension](https://www.php.net/manual/en/book.parallel.php)
to provide a simple interface for dealing with parallelization 
of tasks.

## Usage

The `WorkerPool` requires an implementation of the `WorkFactoryInterface` 
which is responsible for creating the _consumer_ and _producer closures_. 
A producer closure must return a [Generator](https://www.php.net/manual/en/class.generator.php).

### Composer installation

`composer require hdvianna/parallel-workerpool`

### Runing with Docker

`docker-compose up`

Docker compose will build an environment with the needed extensions installed and create a bind mount to the current directory.

### Example

In this example 10 workers will sleep for _n_ milliseconds, each time they 
consume the work generated by the WorkFactory. 

```php
use hdvianna\Concurrent\WorkFactoryInterface;
use hdvianna\Concurrent\WorkerPool;

(new WorkerPool(new class implements WorkFactoryInterface {
    public function createWorkGeneratorClosure(): \Closure
    {
        return function () {
            for ($i = 0; $i < 100; $i++) {
                $work = new \stdClass();
                $work->time = mt_rand(300, 1000);
                $work->id = $i;
                yield $work;
            }
        };
    }

    public function createWorkConsumerClosure(): \Closure
    {
        return function($work) {
            printf("[$work->id]: Sleeping for %d milliseconds ...%s", $work->time, PHP_EOL);
            usleep($work->time * 1000);
            printf("[$work->id]: Woke up after %d milliseconds ...%s", $work->time, PHP_EOL);
        };
    }

}, 10))->run();
```  

### Synchronizing data

Data can be synchronized by using lock and unlock closures sent to the worker functions. 
The shared data are received from the `$lock` closure and sent to the `$unlock` closure.
The last value sent can be get invoking the  `WorkerPool::lastValue()`

```php
use hdvianna\Concurrent\WorkFactoryInterface;
use hdvianna\Concurrent\WorkerPool;

$sharedData = 700;
$works = 1000;

$pool = new WorkerPool((new class ($sharedData, $works) implements WorkFactoryInterface {


    /**
     * @var int
     */
    private $sharedData;

    /**
     * @var int
     */
    private $works;

    /***
     *  constructor.
     * @param int $sharedData
     * @param int $works
     */
    public function __construct($sharedData, $works)
    {
        $this->works = $works;
        $this->sharedData = $sharedData;
    }

    public function createWorkGeneratorClosure(): \Closure
    {
        $workers = $this->works;
        return function () use ($workers) {
            for ($i = 0; $i < $workers; $i++) {
                $work = new \stdClass();
                $work->value = 1;
                yield $work;
            }
        };
    }

    public function createWorkConsumerClosure(): \Closure
    {
        $initialValue = $this->sharedData;
        //Use the $lock and $unlock closures to synchronize data 
        return function ($work, $lock, $unlock) use ($initialValue) {
            /*Synchronize the data. Will block and wait for data. 
            $lock will return the last value*/
            $shared = $lock();            
            if (!isset($shared)) {
                //Data was not initialized 
                $shared = $initialValue;
            }
            $shared += $work->value;
            //Unlocks sending the new data.
            $unlock($shared);
        };
    }

}), 10);
$pool->run();
//Get the last value sent to the unlock closure
$result = $pool->lastValue();
echo("\$result is equals to \$works + \$sharedData?" . PHP_EOL);
echo("($result is equals to $works + $sharedData?)" . PHP_EOL);
echo(assert($result === ($works + $sharedData)) ? "Yes!": "No =(").PHP_EOL;
```
