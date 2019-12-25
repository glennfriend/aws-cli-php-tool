<?php

namespace App\Http\Controllers\Home;

use Exception;
use App\Http\Controllers\Controller;
use App\ThirdParty\Aws;
use App\Entities\Volumn;
use App\Entities\Instance;

/**
 *
 */
class VolumnController extends Controller
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
        return response()->json($this->flow());
    }

    public function perform()
    {
        $result = $this->flow();

        $today = date('c');
        echo "echo '{$today}' >> build.log\n";

        foreach ($result as $row) {
            if (!$row['meta']['build']) {
                continue;
            }
            echo $row['meta']['build'] . " >> build.log\n";
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
        $volumns = collect();
        $regions = $this->aws->regions();
        /*
        $regions = [ // test only
            ["RegionName" => "us-east-2"],
            ["RegionName" => "us-east-1"],
        ];
        */

        foreach ($regions as $region) {
            $region = $region['RegionName'];
            $tempVolumns = $this->aws->volumnsByRegoin($region);
            $tempVolumns = $this->squeezeVolumnsByVolumns($tempVolumns);

            if (!$tempVolumns) {
                continue;
            }

            foreach ($tempVolumns as $tempVolumn) {
                //dump($tempVolumn);
                $volumn = new Volumn($tempVolumn, $region);
                $volumns[] = $volumn;

                // instanceByGregoinAndId
            }
        }

        $result = [];
        foreach ($volumns as $volumn) {
            $tmp = $volumn->dump();
            $tmp['meta'] = [];

            $region = $tmp['region'];
            $instanceId = $tmp['instance-id'];
            if ($instanceId) {

                $instancesReservations = $this->aws->instancesReservationsByGregoinAndId($region, $instanceId);
                $tempInstances = $this->squeezeInstancesByInstancesReservations($instancesReservations);
                if ($tempInstances) {
                    $instanceArray = $tempInstances[0];
                    $instance = new Instance($instanceArray, $region);
                    $settingTags = $this->buildSettingTagsByVolumnAndInstance($volumn, $instance);
                }
            } else {
                $settingTags = [];
            }

            $tmp['meta']['setting_tags'] = $settingTags;
            $tmp['meta']['build'] = $this->buildSettingTagsCommand($volumn, $settingTags);
            // unset($tmp['meta']);
            $result[] = $tmp;
        }

        return $result;
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

    protected function squeezeVolumnsByVolumns(array $tempVolumns)
    {
        if (!$tempVolumns) {
            return [];
        }
        if (!$tempVolumns['Volumes']) {
            return [];
        }

        return $tempVolumns['Volumes'];
    }

    /**
     * @param Volumn $volumn
     * @param Instance $instance
     * @return array
     */
    protected function buildSettingTagsByVolumnAndInstance(Volumn $volumn, Instance $instance)
    {
        $instanceId = $instance->getId();
        $instanceName = $instance->getTag('name');

        return [
            'BU'            => $volumn->getTag('bu', 'Unknown'),
            'Name'          => "{$instanceName} - Volumn",
            'Project'       => $volumn->getTag('project', 'Unknown'),
            'AWS Type'      => 'Volume',
            'Instance Name' => $instanceId,
        ];
    }

    /**
     * volumn tags setting 的邏輯為
     *      - 無論如何都覆蓋
     *
     * @param Volumn $volumn
     * @param array $settingTags
     * @return string
     */
    protected function buildSettingTagsCommand(Volumn $volumn, array $settingTags)
    {
        $region = $volumn->getRegion();
        $instanceId = $volumn->getInstanceId();
        $volumeId = $volumn->getId();
        $profile = $this->aws->getProfile();

        $showTags = [];
        foreach( array_filter($settingTags) as $tag => $value) {
            $showTags[] = "Key='{$tag}',Value='{$value}'";
        }

        if (!$instanceId) {
            return '';
        }
        if (!$profile) {
            return '';
        }

        $cmd = '';
        // $cmd .= "# [instance] {$instanceId}<br>\n";
        // $cmd .= "# [volumn]   {$volumeId}<br>\n";
        $cmd .= "aws ec2 create-tags ";
        $cmd .= "--profile '{$profile}' ";
        $cmd .= "--region '" . sprintf('%-20s', $region) . "' ";
        $cmd .= "--resources '" . sprintf('%-21s', $volumeId) . "' ";
        $cmd .= "--tags " . join('  ', $showTags) . " ";
        // $cmd .= "<br>\n";

        return $cmd;
    }
}
