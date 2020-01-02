<?php

namespace App\Http\Controllers\Home;

use App\Entities\Snapshot;
use Exception;
use App\Http\Controllers\Controller;
use App\ThirdParty\Aws;
use App\Service\SqueezeAwsResource;
use App\Entities\Image;
use Illuminate\Support\Facades\Log;

/**
 *
 */
class ImageController extends Controller
{
    /**
     *
     */
    public function __construct(Aws $aws, SqueezeAwsResource $squeezeAwsResource)
    {
        $this->aws = $aws;
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
        $images = collect();
        $regions = $this->aws->regions();
        /*
        $regions = [ // test only
            ["RegionName" => "us-east-1"],
            ["RegionName" => "us-east-2"],
        ];
        */

        foreach ($regions as $region) {
            $region = $region['RegionName'];
            $tempImages = $this->aws->imagesByRegoin($region);
            $tempImages = $this->squeezeAwsResource->getImagesByImages($tempImages);

            if (!$tempImages) {
                continue;
            }

            foreach ($tempImages as $tempImage) {
                // dump($tempImage);
                $image = new Image($tempImage, $region);
                $images[] = $image;
            }
        }

        $result = [];
        // dd($images);
        foreach ($images as $image) {
            // dd($image);
            $tmp = $image->dump();
            $tmp['meta'] = [];

            $region = $tmp['region'];
            $snapshotId = $image->getMapSnapshotId();
            if (!$snapshotId) {
                continue;
            }

            $tempSnapshots = $this->aws->snapshotsByGregoinAndId($region, $snapshotId);
            $tempSnapshots = $this->squeezeAwsResource->getSnapshotsBySnapshots($tempSnapshots);
            $tempSnapshot = $tempSnapshots[0];
            $snapshot = new Snapshot($tempSnapshot, $region);

            if (!$snapshot) {
                continue;
            }

            $settingTags = $this->buildSettingTagsByImageAndSnapshot($image, $snapshot);

            $tmp['meta']['snapshot_tags'] = $snapshot->getCustomTags();
            $tmp['meta']['setting_tags'] = $settingTags;
            $tmp['meta']['build'] = $this->buildSettingTagsCommand($image, $settingTags);

            // dump($image);
            // dd($tmp);
            // unset($tmp['meta']);
            $result[] = $tmp;
        }

        return $result;
    }

    /**
     * 如果上一層沒有資料, 不要覆蓋現在原本的值
     *
     * @param Image $image
     * @param Snapshot $snapshot
     * @return array
     */
    protected function buildSettingTagsByImageAndSnapshot(Image $image, Snapshot $snapshot): array
    {
        $instanceName = null;
        if ($snapshot) {
            $instanceName = $snapshot->getTag('instance name');
        }
        $snapshotName = $snapshot->getTag('name');
        $startDate = mb_substr($image->data['CreationDate'], 0, 10) . ' UTC';

        return [
            'BU'            => $snapshot->getTag('bu') ?? $image->getTag('bu') ?? 'Unknown',
            'Name'          => "{$snapshotName} Image {$startDate}",
            'Project'       => $snapshot->getTag('project') ?? $image->getTag('project') ?? 'Unknown',
            'Environment'   => $snapshot->getTag('environment') ?? $image->getTag('environment') ?? 'Unknown',
            'AWS Type'      => 'EC2 Image',
            'Instance Name' => $instanceName ?? $snapshot->getTag('instance name') ?? 'Unknown',
        ];
    }

    /**
     * @param Snapshot $image
     * @param array $settingTags
     * @return string
     */
    protected function buildSettingTagsCommand(Image $image, array $settingTags)
    {
        $region = $image->getRegion();
        $imageId = $image->getId();
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
        $cmd .= "--resources '{$imageId}' ";
        $cmd .= "--tags " . join('  ', $showTags) . " ";

        return $cmd;
    }
}
