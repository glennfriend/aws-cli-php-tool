<?php

namespace App\Http\Controllers\Home;

use Exception;
use App\Http\Controllers\Controller;
use App\ThirdParty\Aws;
use App\Service\SqueezeAwsResource;
use App\Entities\Instance;

/**
 *
 */
class InstanceController extends Controller
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
        $instances = collect();
        $regions = $this->aws->regions();
        /*
        $regions = [ // test only
            ["RegionName" => "us-east-2"],
            ["RegionName" => "us-east-1"],
        ];
        */

        foreach ($regions as $region) {
            $region = $region['RegionName'];
            $instancesReservations = $this->aws->instancesReservationsByRegoin($region);

            $tempInstances = $this->squeezeAwsResource->getInstancesByInstancesResource($instancesReservations);
            if (!$tempInstances) {
                continue;
            }

            foreach ($tempInstances as $tempInstance) {
                $instance = new Instance($tempInstance, $region);
                $instances[] = $instance;
            }
        }

        /*
        foreach ($instances as $instance) {
            echo '<pre>';
            var_export($instance->dump());
            var_export($instance);
            echo '</pre>';
        } */

        $result = [];
        foreach ($instances as $instance) {
            $settingTags = $this->buildSettingTagsByInstance($instance);
            $tmp = $instance->dump();
            $tmp['meta'] = [];
            $tmp['meta']['setting_tags'] = $settingTags;
            $tmp['meta']['build'] = $this->buildSettingTagsCommand($instance, $settingTags);
            $result[] = $tmp;
        }

        return $result;
    }

    /**
     * @param Instance $instance
     * @return array
     */
    protected function buildSettingTagsByInstance(Instance $instance)
    {
        $environment = $instance->getTag('environment', 'Production');
        $name = $instance->getTag('name');

        // NOTE: 如果 name 有包含 'staging' 的字, 就將 env 設定為 'Staging'
        if (preg_match('/staging/i', $name)) {
            $environment = 'Staging';
        }

        return [
            'Environment'   => $environment,
            'AWS Type'      => 'EC2 Instance',
            'Instance Name' => $instance->getTag('name'),
        ];
    }

    /**
     * @param Instance $instance
     * @param array $settingTags
     * @return string
     */
    protected function buildSettingTagsCommand(Instance $instance, array $settingTags)
    {
        $region = $instance->getRegion();
        $instanceId = $instance->getId();
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
        $cmd .= "--resources '{$instanceId}' ";
        $cmd .= "--tags " . join('  ', $showTags) . " ";

        return $cmd;
    }

}
