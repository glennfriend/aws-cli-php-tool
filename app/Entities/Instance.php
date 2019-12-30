<?php

namespace App\Entities;

/**
 *
 */
class Instance
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
        return $this->data['InstanceId'];
    }

    /**
     * @return string
     */
    public function getState(): string
    {
        return $this->data['State']['Name'];
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
    public function getNnetworkInterfacesGroupName(): ?string
    {
        if (
            $this->data['NetworkInterfaces'] &&
            $this->data['NetworkInterfaces'][0] &&
            $this->data['NetworkInterfaces'][0]['Groups'] &&
            $this->data['NetworkInterfaces'][0]['Groups'][0] &&
            $this->data['NetworkInterfaces'][0]['Groups'][0]['GroupName']
        ) {
            return $this->data['NetworkInterfaces'][0]['Groups'][0]['GroupName'];
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
            'region'                        => $this->region,
            'id'                            => $this->data['InstanceId'],
            'type'                          => $this->data['InstanceType'],
            'state'                         => $this->getState(),
            'network_interfaces_group_name' => $this->getNnetworkInterfacesGroupName(),
            'tag'                           => $this->getCustomTags(),
        ];
    }
}
