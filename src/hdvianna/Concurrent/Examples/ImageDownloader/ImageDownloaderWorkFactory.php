<?php


namespace hdvianna\Concurrent\Examples\ImageDownloader;


use hdvianna\Concurrent\WorkFactoryInterface;

class ImageDownloaderWorkFactory implements WorkFactoryInterface
{

    private $maximumImages;
    private $imageSavePath;

    /**
     * ImageDownloaderWorkFactory constructor.
     * @param $maximumImages
     * @param $imageSavePath
     */
    public function __construct($maximumImages, $imageSavePath)
    {
        $this->maximumImages = $maximumImages;
        $this->imageSavePath = $imageSavePath;
    }

    public function createWorkGeneratorClosure(): \Closure
    {
        $maximumImages = $this->maximumImages;
        $imageSavePath = $this->imageSavePath;
        return function () use ($maximumImages, $imageSavePath) {
            $imagesProduced = 0;
            while ($imagesProduced < $maximumImages) {
                $imagesProduced++;
                yield "$imageSavePath".DIRECTORY_SEPARATOR.uniqid().".jpg";
            }
        };
    }

    public function createWorkConsumerClosure(): \Closure
    {
        return function($savePath) {
            $url = "https://picsum.photos/800/600/?blur=2";
            $curlHandler = curl_init($url);
            curl_setopt($curlHandler, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($curlHandler, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($curlHandler, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($curlHandler);
            curl_close($curlHandler);
            $fileHandler = fopen($savePath, "w+");
            fwrite($fileHandler, $result);
            fclose($fileHandler);
        };
    }

}