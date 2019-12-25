<?php

namespace App\Entities;

/**
 *
 */
class Volumn
{
    public $data;
    protected $region;

    /**
     * @return void
     */
    public function __construct(array $attribs, string $region)
    {
        $this->data = $attribs;
        $this->region = $region;
    }

    public function getRegion(): string
    {
        return $this->region;
    }


    public function getId(): string
    {
        return $this->data['VolumeId'];
    }

    /**
     * @return string
     */
    public function getState(): string
    {
        return $this->data['State'];
    }

    /**
     * @param string $tagKey
     * @param string|null $defaultValue
     * @return null|string
     */
    public function getTag(string $tagKey, string $defaultValue = null): ?string
    {
        if (!isset($this->data['Tags'])) {
            return $defaultValue;
        }

        foreach ($this->data['Tags'] as $tag) {
            $key = $tag['Key'];
            $value = $tag['Value'];
            if (strtolower($key) == strtolower($tagKey)) {
                return $value;
            }
        }

        return $defaultValue;
    }

    /**
     * @return null|string
     */
    public function getInstanceId(): ?string
    {
        if (
            $this->data['Attachments'] &&
            $this->data['Attachments'][0] &&
            $this->data['Attachments'][0]['InstanceId']
        ) {
            return $this->data['Attachments'][0]['InstanceId'];
        }
        return null;
    }

    /**
     * @return array
     */
    public function dump(): array
    {
        $instanceId = $this->getInstanceId();

        return [
            'region'      => $this->region,
            // 'region-full' => $this->data['AvailabilityZone'],
            'id'          => $this->data['VolumeId'],
            'type'        => $this->data['VolumeType'],
            'state'       => $this->getState(),
            'instance-id' => $instanceId,
            'snapshot-id' => $this->data['SnapshotId'],
            'tag'         => [
                'BU'            => $this->getTag('bu'),
                'Name'          => $this->getTag('name'),
                'Project'       => $this->getTag('project'),
                'AWS Type'      => $this->getTag('aws type'),
                'Instance Name' => $this->getTag('instance name'),
            ],
        ];
    }
}
