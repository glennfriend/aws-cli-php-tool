<?php

namespace App\Http\Controllers\Home;

use Exception;
use App\Http\Controllers\Controller;
use App\ThirdParty\Aws;
use App\Service\SqueezeAwsResource;
use App\Entities\Volume;
use App\Entities\Instance;

/**
 *
 */
class VolumeController extends Controller
{
    /**
     *
     */
    public function __construct(Aws $aws, SqueezeAwsResource $squeezeAwsResource)
    {
        $this->aws = $aws;
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
        $volumes = collect();
        $regions = $this->aws->regions();
        /*
        $regions = [ // test only
            ["RegionName" => "us-east-2"],
            ["RegionName" => "us-east-1"],
        ];
        */

        foreach ($regions as $region) {
            $region = $region['RegionName'];
            $tempVolumes = $this->aws->volumesByRegoin($region);
            $tempVolumes = $this->squeezeAwsResource->getVolumesByVolumesResource($tempVolumes);

            if (!$tempVolumes) {
                continue;
            }

            foreach ($tempVolumes as $tempVolume) {
                //dump($tempVolume);
                $volume = new Volume($tempVolume, $region);
                $volumes[] = $volume;
            }
        }

        $result = [];
        foreach ($volumes as $volume) {
            $tmp = $volume->dump();
            $tmp['meta'] = [];

            $region = $tmp['region'];
            $instanceId = $tmp['instance-id'];

            $instance = null;
            $instanceTags = [];
            if ($instanceId) {

                $instancesReservations = $this->aws->instancesReservationsByGregoinAndId($region, $instanceId);
                $tempInstances = $this->squeezeAwsResource->getInstancesByInstancesResource($instancesReservations);

                if ($tempInstances) {
                    $instanceArray = $tempInstances[0];
                    $instance = new Instance($instanceArray, $region);
                    $instanceTags = $instance->getCustomTags();
                }
            }
            $settingTags = $this->buildSettingTagsByVolumeAndInstance($volume, $instance);

            $tmp['meta']['instance_tags'] = $instanceTags;
            $tmp['meta']['setting_tags'] = $settingTags;
            $tmp['meta']['build'] = $this->buildSettingTagsCommand($volume, $settingTags);
            // unset($tmp['meta']);
            $result[] = $tmp;
        }

        return $result;
    }

    /**
     * @param Volume $volume
     * @param Instance $instance
     * @return array
     */
    protected function buildSettingTagsByVolumeAndInstance(Volume $volume, Instance $instance=null)
    {
        if (!$instance) {
            return [
                'BU'            => 'Unknown',
                'Name'          => $volume->getTag('name', 'Unknown'),
                'Project'       => 'Unknown',
                'Environment'   => $volume->getTag('environment', 'Unknown'),
                'AWS Type'      => 'EC2 Volume',
                'Instance Name' => 'Unknown',
            ];
        }

        $instanceName = $instance->getTag('name');
        $environment = $instance->getTag('environment', 'Production');
        return [
            'BU'            => $instance->getTag('bu', 'Unknown'),
            'Name'          => "{$instanceName} Volume",
            'Project'       => $instance->getTag('project', 'Unknown'),
            'Environment'   => $environment,
            'AWS Type'      => 'EC2 Volume',
            'Instance Name' => $instanceName,
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
    protected function buildSettingTagsCommand(Volume $volume, array $settingTags)
    {
        $region = $volume->getRegion();
        $volumeId = $volume->getId();
        $profile = $this->aws->getProfile();

        $showTags = [];
        foreach (array_filter($settingTags) as $tag => $value) {
            $showTags[] = "Key='{$tag}',Value='{$value}'";
        }

        if (!$profile) {
            return '';
        }

        $cmd = '';
        // $cmd .= "# [instance] {$instanceId}<br>\n";
        // $cmd .= "# [volume]   {$volumeId}<br>\n";
        $cmd .= "aws ec2 create-tags ";
        $cmd .= "--profile '{$profile}' ";
        $cmd .= "--region '{$region}' ";
        $cmd .= "--resources '" . sprintf('%-21s', $volumeId) . "' ";
        $cmd .= "--tags " . join('  ', $showTags) . " ";
        // $cmd .= "<br>\n";

        return $cmd;
    }
}
