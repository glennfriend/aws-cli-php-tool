<?php

namespace App\Http\Controllers\Home;

use Exception;
use App\Http\Controllers\Controller;
use App\ThirdParty\Aws;
use App\Entities\Instance;

/**
 *
 */
class InstanceController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(Aws $aws)
    {
        $this->aws = $aws;
        $this->aws->setProfile('default');
        // $this->aws->setProfile('rto');
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

            $tempInstances = $this->squeezeInstancesByInstancesReservations($instancesReservations);
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


    protected function squeezeInstancesByInstancesReservations(array $instancesReservations)
    {
        if (!$instancesReservations) {
            return [];
        }
        if (!$instancesReservations['Reservations']) {
            return [];
        }

        $instances = [];
        foreach ($instancesReservations['Reservations'] as $reservations) {
            if (count($reservations['Instances']) > 1) {
                // 如果發現未知的情況
                throw new Exception('Reservation more Instances problem');
            }
            $instances[] = $reservations['Instances'][0];
        }

        return $instances;
    }

    /**
     * @param Volumn $volumn
     * @param Instance $instance
     * @return array
     */
    protected function buildSettingTagsByInstance(Instance $instance)
    {
        return [
            'tag' => [
                'Environment'   => $instance->getTag('Environment', 'Production'),
                'AWS Type'      => 'Instance',
                'Instance Name' => $instance->getId(),
            ]
        ];
    }

}
