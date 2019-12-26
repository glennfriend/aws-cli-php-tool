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
        $profile = env('AWS_PROFILE');
        $ownerId = env('AWS_OWNER_ID');

        $this->aws = $aws;
        $this->aws->setOwnerId($ownerId);
        $this->aws->setProfile($profile);

        $this->squeezeAwsResource = $squeezeAwsResource;
    }

    public function show()
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
            $tmp = $instance->dump();
            $tmp['meta'] = [];
            $tmp['meta']['setting_tags'] = $this->buildSettingTagsByInstance($instance);

            $result[] = $tmp;
        }

        return response()->json($result);
    }

    /**
     * @param Instance $instance
     * @return array
     */
    protected function buildSettingTagsByInstance(Instance $instance)
    {
        return [
            'tag' => [
                'Environment'   => $instance->getTag('environment', 'Production'),
                'AWS Type'      => 'Instance',
                'Instance Name' => $instance->getTag('name'),
            ]
        ];
    }

}
