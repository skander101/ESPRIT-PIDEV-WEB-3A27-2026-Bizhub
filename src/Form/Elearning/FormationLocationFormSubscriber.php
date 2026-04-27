<?php

declare(strict_types=1);

namespace App\Form\Elearning;

use App\Entity\Elearning\Formation;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

final class FormationLocationFormSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SUBMIT => 'onPreSubmit',
            FormEvents::POST_SUBMIT => 'onPostSubmit',
        ];
    }

    public function onPreSubmit(FormEvent $event): void
    {
        if (!$event->getForm()->isRoot()) {
            return;
        }

        $data = $event->getData();
        if (!is_array($data)) {
            return;
        }

        if ($this->isOnlineFromFormPayload($data['en_ligne'] ?? null)) {
            $data['lieu'] = null;
            $data['latitude'] = null;
            $data['longitude'] = null;
            $event->setData($data);
        }
    }

    public function onPostSubmit(FormEvent $event): void
    {
        if (!$event->getForm()->isRoot()) {
            return;
        }

        $data = $event->getData();
        if (!$data instanceof Formation) {
            return;
        }

        if ($data->isEnLigne()) {
            $data->setLieu(null);
            $data->setLatitude(null);
            $data->setLongitude(null);
        }
    }

    private function isOnlineFromFormPayload(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1' || $value === 'true') {
            return true;
        }

        return false;
    }
}
