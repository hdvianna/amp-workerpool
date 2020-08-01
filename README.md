# Parallel Worker Pool

Parallel worker pool uses the PHP parallel extension [https://www.php.net/manual/en/book.parallel.php](https://www.php.net/manual/en/book.parallel.php)
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

Data can be synchronized by using channels. The following example creates a channel of length equals to one. In this case, just one worker at a time will be able to receive the data by invoking the method `Channel::recv()`. The other workers will be locked until the woker invokes the method `Channel::send()`.

```php
use hdvianna\Concurrent\WorkFactoryInterface;
use hdvianna\Concurrent\WorkerPool;
use parallel\Channel;

$sharedData = 700;
$works = 1000;

$factory = new class ($sharedData, $works) implements WorkFactoryInterface {

    /**
     * @var parallel\Channel;
     */
    private $channel;

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
        //Creates a channel of length 1
        $this->channel = new Channel(1);
        //Initializes the shared data
        $this->channel->send($sharedData);
        $this->works = $works;
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
        $channel = $this->channel;
        return function ($work) use ($channel) {
            //As the channel length is equals to one, just one worker will proceed. The others will wait
            $shared = $channel->recv();
            //Sends the data and unlocks the next worker
            $channel->send($shared + $work->value);
        };
    }

    public function result()
    {
        return $this->channel->recv();
    }

};
(new WorkerPool($factory, 10))->run();
$result = $factory->result();
echo("\$result is equals to \$works + \$sharedData".PHP_EOL);
echo("$result = $works + $sharedData".PHP_EOL);
assert($result === ($works + $sharedData));
```
