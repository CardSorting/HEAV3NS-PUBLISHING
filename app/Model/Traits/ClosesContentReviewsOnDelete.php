<?php

namespace App\Model\Traits;

use App\Model\ContentReview;
use App\Model\Post;
use App\Model\Reel;

trait ClosesContentReviewsOnDelete
{
    /** Keep ordinary admin deletes and their pending reviews in the same transaction. */
    public function delete()
    {
        if (!$this->exists) {
            return parent::delete();
        }

        return $this->getConnection()->transaction(function () {
            // Match publication decisions' lock order: parent first, then its reviews.
            $current = $this->newQuery()->lockForUpdate()->find($this->getKey());
            if (!$current) {
                return false;
            }

            $this->setRawAttributes($current->getAttributes(), true);
            $this->setRelations([]);
            $deleted = parent::delete();
            if ($deleted) {
                ContentReview::on($this->getConnectionName())
                    ->where('source_type', match (true) {
                        $this instanceof Post => 'post', $this instanceof Reel => 'reel', default => 'story',
                    })
                    ->where('source_id', $this->getKey())
                    ->where('status', ContentReview::STATUS_PENDING)
                    ->update(['status' => ContentReview::STATUS_CANCELLED]);
            }

            return $deleted;
        });
    }
}
