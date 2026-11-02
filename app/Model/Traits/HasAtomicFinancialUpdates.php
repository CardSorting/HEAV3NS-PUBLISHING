<?php

namespace App\Model\Traits;

trait HasAtomicFinancialUpdates
{
    /** Keep observer effects and the request update in one transaction, including ordinary admin form saves. */
    public function save(array $options = [])
    {
        return $this->getConnection()->transaction(function () use ($options) {
            if ($this->exists) {
                $changes = $this->getDirty();
                $current = $this->newQuery()->lockForUpdate()->findOrFail($this->getKey());

                // Rebase only intended edits onto the locked state. Stale status and processed flags cannot replay effects.
                $this->setRawAttributes($current->getAttributes(), true);
                $this->setRawAttributes(array_replace($current->getAttributes(), $changes));
                $this->setRelations([]);
            }

            $saved = parent::save($options);
            if (!$saved) {
                throw new \RuntimeException('The financial request could not be saved.');
            }

            return $saved;
        });
    }

    public function delete()
    {
        if (!$this->exists) {
            return parent::delete();
        }

        return $this->getConnection()->transaction(function () {
            $current = $this->newQuery()->lockForUpdate()->find($this->getKey());
            if (!$current) {
                return false;
            }

            $this->setRawAttributes($current->getAttributes(), true);
            $this->setRelations([]);

            $deleted = parent::delete();
            if ($deleted === false) {
                throw new \RuntimeException('The financial request could not be deleted.');
            }

            return $deleted;
        });
    }
}
