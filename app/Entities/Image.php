<?php

namespace App\Entities;

use App\Entities\EntityHelp\ImageEntityHelp;

/**
 *
 */
class Image
{
    public $data;
    protected $region;

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
        return $this->data['ImageId'];
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
    public function getMapSnapshotId(): ?string
    {
        if (
            $this->data['BlockDeviceMappings'] &&
            $this->data['BlockDeviceMappings'][0] &&
            $this->data['BlockDeviceMappings'][0]['Ebs'] &&
            $this->data['BlockDeviceMappings'][0]['Ebs']['SnapshotId']
        ) {
            $snapshotId = $this->data['BlockDeviceMappings'][0]['Ebs']['SnapshotId'];
        } else {
            return null;
        }

        return $snapshotId;
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
            'region'      => $this->region,
            'id'          => $this->getId(),
            'state'       => $this->getState(),
            'snapshot-id' => $this->getMapSnapshotId(),
            'tag'         => $this->getCustomTags(),
        ];
    }

    // --------------------------------------------------------------------------------
    //  lazy loading
    // --------------------------------------------------------------------------------

    /**
     * @return ImageEntityHelp
     */
    public function factoryHelp(): ImageEntityHelp
    {
        return new ImageEntityHelp($this);
    }

}
