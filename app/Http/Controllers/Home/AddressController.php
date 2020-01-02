<?php

namespace App\Http\Controllers\Home;

use Exception;
use App\Http\Controllers\Controller;
use App\ThirdParty\Aws;
use App\Service\SqueezeAwsResource;
use App\Entities\Instance;
use App\Entities\Volume;
use App\Entities\Address;
use Illuminate\Support\Facades\Log;

/**
 *
 */
class AddressController extends Controller
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
        $addresses = collect();
        $regions = $this->aws->regions();
        /*
        $regions = [ // test only
            ["RegionName" => "us-east-1"],
            ["RegionName" => "us-east-2"],
        ];
        */

        foreach ($regions as $region) {
            $region = $region['RegionName'];
            $tempAddresses = $this->aws->addressesByRegoin($region);

            $tempAddresses = $this->squeezeAwsResource->getAddressesByAddresses($tempAddresses);
            if (!$tempAddresses) {
                continue;
            }

            foreach ($tempAddresses as $tempAddress) {
                // dump($tempSnapshot);
                $address = new Address($tempAddress, $region);
                $addresses[] = $address;
            }
        }

        $result = [];
        foreach ($addresses as $address) {
            $tmp = $address->dump();
            $tmp['meta'] = [];

            $region = $tmp['region'];
            $instanceId = $address->getInstanceId();
            $allocationId = $address->getAllocationId();
            if (! $allocationId) {
                continue;
            }

            $settingTags = [];
            $instanceTags = [];

            if ($instanceId) {
                $instancesReservations = $this->aws->instancesReservationsByGregoinAndId($region, $instanceId);
                $instances = $this->squeezeAwsResource->getInstancesByInstancesResource($instancesReservations);
                if ($instances && isset($instances[0])) {
                    $instance = new Instance($instances[0], $region);
                    $instanceTags = $instance->getCustomTags();
                    $settingTags = $this->buildSettingTagsByInstance($instance);
                }
            }

            $tmp['meta']['instance_tags'] = $instanceTags;
            $tmp['meta']['setting_tags'] = $settingTags;
            $tmp['meta']['build'] = $this->buildSettingTagsCommand($address, $settingTags);
            $result[] = $tmp;
        }

        return $result;
    }

    /**
     * @param $instance
     * @return array
     */
    protected function buildSettingTagsByInstance($instance): array
    {
        if (!$instance) {
            return [];
        }

        $instanceName = $instance->getTag('name');

        // NOTE: 如果 name 有包含 'staging' 的字, 就將 env 設定為 'Staging'
        if (preg_match('/staging/i', $instanceName)) {
            $environment = 'Staging';
        }
        else {
            $environment = $instance->getTag('environment', 'Production');
        }

        return [
            'BU'            => $instance->getTag('bu', 'Unknown'),
            'Name'          => "{$instanceName} Address",
            'Project'       => $instance->getTag('project', 'Unknown'),
            'Environment'   => $environment,
            'AWS Type'      => 'EC2 Address',
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
    protected function buildSettingTagsCommand(Address $address, array $settingTags)
    {
        $region = $address->getRegion();
        $allocationId = $address->getAllocationId();
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
        $cmd .= "--resources '{$allocationId}' ";
        $cmd .= "--tags " . join('  ', $showTags) . " ";

        return $cmd;
    }
}
