<?php

namespace App\Entities;

/**
 *
 */
class Snapshot
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
        return $this->data['SnapshotId'];
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

    public function getCustomTags()
    {
        return [
            'BU'            => $this->getTag('bu'),
            'Name'          => $this->getTag('name'),
            'Project'       => $this->getTag('project'),
            'Environment'   => $this->getTag('environment'),
            'AWS Type'      => $this->getTag('aws type'),
            'Instance Name' => $this->getTag('instance name')
        ];
    }

    /**
     * @return array
     */
    public function dump(): array
    {
        return [
            'region'    => $this->region,
            'id'        => $this->getId(),
            'state'     => $this->getState(),
            'volume-id' => $this->data['VolumeId'],
            'tag'       => $this->getCustomTags(),
        ];
    }
}
