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
     * profile default is 'default'
     * @var string
     */
    protected $profile = '';

    /**
     * @param string $profile
     */
    public function setProfile(string $profile)
    {
        $this->profile = $profile;
    }

    public function getProfile(): string
    {
        return $this->profile;
    }

    // --------------------------------------------------------------------------------
    //  regions
    // --------------------------------------------------------------------------------

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

    // --------------------------------------------------------------------------------
    //  instances
    // --------------------------------------------------------------------------------

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
            throw new Exception('instances not found');
        }

        $instances = json_decode($result, true);
        return $instances;
    }

    public function instancesReservationsByGregoinAndId(string $region, string $instanceId): array
    {
        $cacheKey = "region-{$region}-instance-{$instanceId}";
        $cmd = "aws ec2 describe-instances --region '{$region}' --instance-ids '{$instanceId}'";
        $result = $this->executeCommandAndCache($cmd, $cacheKey);

        if (!$result) {
            throw new Exception('instance not found');
        }

        $instance = json_decode($result, true);
        return $instance;
    }

    // --------------------------------------------------------------------------------
    //  volumns
    // --------------------------------------------------------------------------------

    /**
     * @param string $region
     * @return array
     * @throws Exception
     */
    public function volumnsByRegoin(string $region): array
    {
        $cacheKey = "region-{$region}-all-volumns";
        $cmd = "aws ec2 describe-volumes --region '{$region}'";
        $result = $this->executeCommandAndCache($cmd, $cacheKey);

        if (!$result) {
            throw new Exception('volumns not found');
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
     *      - use --profile option
     *
     * @param string $cmd
     * @param string $cacheKey
     * @return null|string
     */
    protected function executeCommandAndCache(string $cmd, string $cacheKey)
    {
        $debug = true;
        $debugInfo = [];

        if (!$this->profile) {
            throw new Exception('Error: aws --profile not fould !');
        }

        $file = $this->file($this->profile . '-' . $cacheKey);
        // echo $file."<Br>\n";

        if (file_exists($file)) {
            $debugInfo [] = "used cache";
        } else {
            $command = "{$cmd} --profile '{$this->profile}' > '{$file}'";

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

        $result = (string) file_get_contents($file);
        if (! $result) {
            throw new Exception("empty content in `{$file}`");
        }

        return $result;
    }

}
