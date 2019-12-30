<?php

namespace App\Service;

use Exception;
use App\Http\Controllers\Controller;
use App\ThirdParty\Aws;
use App\Entities\Instance;

/**
 * 從 AWS 來的資料需要再經過一些處理
 */
class SqueezeAwsResource
{
    public function getInstancesByInstancesResource(array $instancesResource)
    {
        if (!$instancesResource) {
            return [];
        }
        if (!$instancesResource['Reservations']) {
            return [];
        }

        $instances = [];
        foreach ($instancesResource['Reservations'] as $reservations) {
            if (count($reservations['Instances']) > 1) {
                // 如果發現未知的情況
                throw new Exception('Reservation more Instances problem');
            }
            $instances[] = $reservations['Instances'][0];
        }

        return $instances;
    }

    public function getVolumesByVolumesResource(array $volumesResource)
    {
        if (!$volumesResource) {
            return [];
        }
        if (!$volumesResource['Volumes']) {
            return [];
        }

        return $volumesResource['Volumes'];
    }

    public function getSnapshotsBySnapshots(array $snapshotsResource)
    {
        if (!$snapshotsResource) {
            return [];
        }
        if (!$snapshotsResource['Snapshots']) {
            return [];
        }

        $result = [];
        foreach ($snapshotsResource['Snapshots'] as $snapshot)
        {
            if (!isset($snapshot['VolumeId'])) {
                continue;
            }
            if ($snapshot['VolumeId'] == 'vol-ffffffff') {
                throw new Exception('如果取得 vol-ffffffff 代表你找錯資料, 不應該找 snapshot by public, 應該是 owned by me 的資料');
            }

            $result[] = $snapshot;
        }

        return $result;
    }

    public function getAddressesByAddresses(array $addressesResource)
    {
        if (!$addressesResource) {
            return [];
        }
        if (!$addressesResource['Addresses']) {
            return [];
        }

        $result = [];
        foreach ($addressesResource['Addresses'] as $address)
        {
            if (!isset($address['InstanceId'])) {
                continue;
            }
            if (!isset($address['AssociationId'])) {
                continue;
            }

            $result[] = $address;
        }

        return $result;
    }
}
