<?php

namespace App\Entities\EntityHelp;

use App\ThirdParty\Aws;
use App\Service\SqueezeAwsResource;
use App\Entities\Snapshot;
use App\Entities\Image;

/**
 *
 */
class ImageEntityHelp
{
    protected $entity;

    public function __construct(Image $image)
    {
        $this->entity = $image;
        $this->aws = app(Aws::class);
        $this->squeezeAwsResource = app(SqueezeAwsResource::class);
    }

    // --------------------------------------------------------------------------------
    //  lazy loading
    // --------------------------------------------------------------------------------

    /**
     * @return Snapshot|null
     */
    public function fetchSnapshot(): ?Snapshot
    {
        $snapshotId = $this->entity->getMapSnapshotId();
        if (!$snapshotId) {
            null;
        }

        $region = $this->entity->getRegion();

        $tempSnapshots = $this->aws->snapshotsByGregoinAndId($region, $snapshotId);
        $tempSnapshots = $this->squeezeAwsResource->getSnapshotsBySnapshots($tempSnapshots);
        $tempSnapshot = $tempSnapshots[0];
        return new Snapshot($tempSnapshot, $region);
    }

}
