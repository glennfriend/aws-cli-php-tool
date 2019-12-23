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
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(array $attribs, string $region)
    {
        $this->data = $attribs;
        $this->region = $region;
    }

    public function getState()
    {
        return $this->data['State']['Name'];
    }

    /**
     * @param string $tagKey
     * @return string
     */
    public function getTag(string $tagKey): string
    {
        if (!isset($this->data['Tags'])) {
            return '';
        }

        foreach ($this->data['Tags'] as $tag) {
            $key = $tag['Key'];
            $value = $tag['Value'];
            if (strtolower($key) == strtolower($tagKey)) {
                return $value;
            }
        }

        return '';
    }

    /**
     * @return string
     */
    public function getNnetworkInterfacesGroupName()
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
        return '';
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
            'tag'                           => [
                'BU'            => $this->getTag('bu'),
                'Name'          => $this->getTag('name'),
                'Project'       => $this->getTag('project'),
                'AWS-Type'      => 'instance',
                'Instance-Name' => "instance-{$this->data['InstanceId']}",
            ]
        ];
    }
}
