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

    public function setProfile(string $profile)
    {
        $this->profile = $profile;
    }

    public function getProfile(): string
    {
        return $this->profile;
    }

    public function setOwnerId(string $ownerId)
    {
        $this->ownerId = $ownerId;
    }

    public function getOwnerId(): string
    {
        return $this->ownerId;
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
    //  volumes
    // --------------------------------------------------------------------------------

    /**
     * @param string $region
     * @return array
     * @throws Exception
     */
    public function volumesByRegoin(string $region): array
    {
        $cacheKey = "region-{$region}-all-volumes";
        $cmd = "aws ec2 describe-volumes --region '{$region}'";
        $result = $this->executeCommandAndCache($cmd, $cacheKey);

        if (!$result) {
            throw new Exception('volumes not found');
        }

        $instances = json_decode($result, true);
        return $instances;
    }

    public function volumesByGregoinAndId(string $region, string $volumeId): array
    {
        $cacheKey = "region-{$region}-volume-{$volumeId}";
        $cmd = "aws ec2 describe-volumes --region '{$region}' --volume-ids '{$volumeId}'";
        // echo $cmd . "\n";
        $result = $this->executeCommandAndCache($cmd, $cacheKey);

        if (!$result) {
            throw new Exception('volume not found');
        }

        $instance = json_decode($result, true);
        return $instance;
    }

    // --------------------------------------------------------------------------------
    //  snapshots
    // --------------------------------------------------------------------------------

    public function snapshotsByRegoin(string $region): array
    {
        $cacheKey = "region-{$region}-all-snapshots";
        $cmd = "aws ec2 describe-snapshots --region '{$region}' --owner-id '{$this->ownerId}'";
        $result = $this->executeCommandAndCache($cmd, $cacheKey);

        if (!$result) {
            throw new Exception('snapshots not found');
        }

        $snapshots = json_decode($result, true);
        return $snapshots;
    }

    /*
    public function snapshotsByGregoinAndId(string $region, string $snapshotId): array
    {
        $cacheKey = "region-{$region}-snapshot-{$snapshotId}";
        $cmd = "aws ec2 describe-snapshots --region '{$region}' --snapshot-ids '{$snapshotId}'";
        $result = $this->executeCommandAndCache($cmd, $cacheKey);

        if (!$result) {
            throw new Exception('snapshot not found');
        }

        $instance = json_decode($result, true);
        return $instance;
    }
    */

    // --------------------------------------------------------------------------------
    //  Elastic IP
    // --------------------------------------------------------------------------------

    /**
     * Elastic IP addresses
     */
    public function addressesByRegoin(string $region): array
    {
        $cacheKey = "region-{$region}-all-address";
        $cmd = "aws ec2 describe-addresses --region '{$region}' ";
        $result = $this->executeCommandAndCache($cmd, $cacheKey);

        if (!$result) {
            throw new Exception('addresses not found');
        }

        $snapshots = json_decode($result, true);
        return $snapshots;
    }

    public function addressesByGregoinAndAllocationId(string $region, string $allocationId): array
    {
        $cacheKey = "region-{$region}-allocation-{$allocationId}";
        $cmd = "aws ec2 describe-addresses --region '{$region}' --allocation-ids '{$allocationId}'";
        // echo $cmd . "\n";
        $result = $this->executeCommandAndCache($cmd, $cacheKey);

        if (!$result) {
            throw new Exception('volume not found');
        }

        $instance = json_decode($result, true);
        return $instance;
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
     * @throws Exception
     */
    protected function executeCommandAndCache(string $cmd, string $cacheKey)
    {
        if (!$this->profile) {
            throw new Exception('Error: aws --profile not fould !');
        }
        if (!$this->ownerId) {
            throw new Exception('Error: aws --owner-id not fould !');
        }

        $file = $this->file($this->profile . '-' . $cacheKey);
        // echo $file."<Br>\n";

        if (file_exists($file)) {
            $this->log("used cache in {$file}");
        } else {
            $command = "{$cmd} --profile '{$this->profile}' > '{$file}'";

            $this->log("command: {$command}");

            $folder = dirname($file);
            if (!file_exists($folder)) {
                $this->log("mkdir data folder");
                mkdir($folder);
            }

            system($command);
            $this->log("create content by aws-cli");

            if (!file_exists($file)) {
                $this->log("create content fail !");
                return null;
            }
        }

        $result = (string)file_get_contents($file);
        if (!$result) {
            // throw new Exception("empty content in `{$file}`");
        }

        return $result;
    }

    protected function log($message)
    {
        log::info($message);
    }
}
