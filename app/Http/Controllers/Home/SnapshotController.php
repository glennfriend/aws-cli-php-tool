<?php

namespace App\Http\Controllers\Home;

use Exception;
use App\Http\Controllers\Controller;
use App\ThirdParty\Aws;
use App\Service\SqueezeAwsResource;
use App\Entities\Instance;
use App\Entities\Volume;
use App\Entities\Snapshot;
use Illuminate\Support\Facades\Log;

/**
 *
 */
class SnapshotController extends Controller
{
    /**
     *
     */
    public function __construct(Aws $aws, SqueezeAwsResource $squeezeAwsResource)
    {
        $profile = env('AWS_PROFILE');
        $ownerId = env('AWS_OWNER_ID');

        $this->aws = $aws;
        $this->aws->setOwnerId($ownerId);
        $this->aws->setProfile($profile);

        $this->squeezeAwsResource = $squeezeAwsResource;
    }

    public function show()
    {
        return response()->json($this->flow());
    }

    public function perform()
    {
        $result = $this->flow();

        $today = date('c');
        echo "echo '{$today}'" . "\n";

        foreach ($result as $row) {
            if (!$row['meta']['build']) {
                continue;
            }
            echo $row['meta']['build'] . "\n";
        }
    }

    // --------------------------------------------------------------------------------
    //  private
    // --------------------------------------------------------------------------------

    /**
     * @return array
     */
    protected function flow(): array
    {
        $snapshots = collect();
        $regions = $this->aws->regions();
        /*
        $regions = [ // test only
            ["RegionName" => "us-east-1"],
            ["RegionName" => "us-east-2"],
        ];
        */

        foreach ($regions as $region) {
            $region = $region['RegionName'];
            $tempSnapshots = $this->aws->snapshotsByRegoin($region);
            $tempSnapshots = $this->squeezeAwsResource->getSnapshotsBySnapshots($tempSnapshots);

            if (!$tempSnapshots) {
                continue;
            }

            foreach ($tempSnapshots as $tempSnapshot) {
                // dump($tempSnapshot);
                $snapshot = new Snapshot($tempSnapshot, $region);
                $snapshots[] = $snapshot;
            }
        }

        $result = [];
        foreach ($snapshots as $snapshot) {
            $tmp = $snapshot->dump();
            $tmp['meta'] = [];

            $region = $tmp['region'];
            $volumeId = $tmp['volume-id'];

            $instance = null;
            $settingTags = [];
            $volumeTags = [];
            if ($volumeId) {

                try {
                    $volumes = $this->aws->volumesByGregoinAndId($region, $volumeId);
                } catch (Exception $exception) {
                    Log::error($exception->getMessage());
                    continue;
                }

                $tempVolumes = $this->squeezeAwsResource->getVolumesByVolumesResource($volumes);
                $tempVolume = $tempVolumes[0];
                if ($tempVolume) {
                    $volume = new Volume($tempVolume, $region);
                    $volumeTags = $volume->getCustomTags();
                    $instanceId = $volume->getInstanceId();

                    if ($instanceId) {
                        $instancesReservations = $this->aws->instancesReservationsByGregoinAndId($region, $instanceId);
                        $instances = $this->squeezeAwsResource->getInstancesByInstancesResource($instancesReservations);
                        if ($instances && isset($instances[0])) {
                            $instance = new Instance($instances[0], $region);
                        }
                    }

                    $settingTags = $this->buildSettingTagsBySnapshotAndVolume($snapshot, $volume, $instance);
                }
            }

            $tmp['meta']['volume_tags'] = $volumeTags;
            $tmp['meta']['setting_tags'] = $settingTags;
            $tmp['meta']['build'] = $this->buildSettingTagsCommand($snapshot, $settingTags);

            // dump($snapshot);
            // dd($tmp);
            // unset($tmp['meta']);
            $result[] = $tmp;
        }

        return $result;
    }

    /**
     * @param Snapshot $snapshot
     * @param Volume $volume
     * @param $instance
     * @return array
     */
    protected function buildSettingTagsBySnapshotAndVolume(Snapshot $snapshot, Volume $volume, $instance): array
    {
        $instanceName = null;
        if ($instance) {
            $instanceName = $instance->getTag('name');
        }
        $volumeName = $volume->getTag('name');
        $startDate = mb_substr($snapshot->data['StartTime'], 0, 10) . ' UTC';

        // NOTE: 如果 name 有包含 'staging' 的字, 就將 env 設定為 'Staging'
        if (preg_match('/staging/i', $volumeName)) {
            $environment = 'Staging';
        }
        else {
            $environment = $volume->getTag('environment', 'Production');
        }

        return [
            'BU'            => $volume->getTag('bu', 'Unknown'),
            'Name'          => "{$volumeName} Snapshot " . $startDate,
            'Project'       => $volume->getTag('project', 'Unknown'),
            'Environment'   => $environment,
            'AWS Type'      => 'Snapshot',
            'Instance Name' => $instanceName ?? 'Unknown',
        ];
    }

    /**
     * volume tags setting 的邏輯為
     *      - 無論如何都覆蓋
     *
     * @param Volume $volume
     * @param array $settingTags
     * @return string
     */
    protected function buildSettingTagsCommand(Snapshot $snapshots, array $settingTags)
    {
        $region = $snapshots->getRegion();
        $snapshotId = $snapshots->getId();
        $profile = $this->aws->getProfile();

        $showTags = [];
        foreach (array_filter($settingTags) as $tag => $value) {
            $showTags[] = "Key='{$tag}',Value='{$value}'";
        }

        if (!$profile) {
            return '';
        }

        $cmd = '';
        $cmd .= "aws ec2 create-tags ";
        $cmd .= "--profile '{$profile}' ";
        $cmd .= "--region '{$region}' ";
        $cmd .= "--resources '" . sprintf('%-21s', $snapshotId) . "' ";
        $cmd .= "--tags " . join('  ', $showTags) . " ";

        return $cmd;
    }
}
