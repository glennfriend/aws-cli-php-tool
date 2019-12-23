<?php

namespace App\ThirdParty;

use Log;
use Exception;
use Illuminate\Support\Str;

/**
 * Aws Cli
 *
 * @package App\ThirdParty
 */
class Aws
{
    /**
     * @return array
     * @throws Exception
     */
    public function regions(): array
    {
        $cacheKey = "all-regions";
        $cmd = "aws ec2 describe-regions";
        $result = $this->executeCommandAndCache($cmd, $cacheKey);
        if (!$result) {
            throw new Exception('regions not found');
        }

        $regions = json_decode($result, true);
        return $regions['Regions'];
    }

    /**
     * @param string $region
     * @return array
     * @throws Exception
     */
    public function instancesReservationsByRegoin(string $region): array
    {
        $cacheKey = "region-{$region}-all-instances-reservations";
        $cmd = "aws ec2 describe-instances --region '{$region}'";
        $result = $this->executeCommandAndCache($cmd, $cacheKey);

        if (!$result) {
            throw new Exception('instance not found');
        }

        $instances = json_decode($result, true);
        return $instances;
    }

    // --------------------------------------------------------------------------------
    //  private
    // --------------------------------------------------------------------------------

    /**
     * @param string $key
     * @return string
     */
    protected function file(string $key): string
    {
        $key = Str::slug($key);
        return storage_path("data/{$key}.json");
    }

    /**
     * feature
     *      - execute command
     *      - create cache folder
     *      - create cache data
     *
     * @param string $cmd
     * @param string $cacheKey
     * @return null|string
     */
    protected function executeCommandAndCache(string $cmd, string $cacheKey)
    {
        $debug = false;
        $debugInfo = [];

        $file = $this->file($cacheKey);
        if (file_exists($file)) {
            $debugInfo [] = "used cache";
        } else {
            $command = "{$cmd} > '{$file}'";
            $debugInfo [] = "command: {$command}";

            $folder = dirname($file);
            if (!file_exists($folder)) {
                $debugInfo [] = "mkdir data folder";
                mkdir($folder);
            }

            system($command);
            $debugInfo [] = "create content by aws-cli";

            if (!file_exists($file)) {
                $debugInfo [] = "create content fail !";
                if ($debug) {
                    Log::info($debugInfo);
                }
                return null;
            }
        }

        if ($debug) {
            Log::info($debugInfo);
        }

        return (string)file_get_contents($file);
    }

}
