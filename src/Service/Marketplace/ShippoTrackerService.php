<?php

namespace App\Service\Marketplace;

class ShippoTrackerService
{
    private bool $available = false;

    public function __construct(private readonly string $shippoApiKey)
    {
        if ($this->shippoApiKey !== '' && class_exists(\Shippo::class, false)) {
            \Shippo::setApiKey($this->shippoApiKey);
            $this->available = true;
        }
    }

    /**
     * Returns Shippo tracking data or null on failure.
     * Response keys: carrier, tracking_number, tracking_status, tracking_history, eta, address_to
     */
    public function getTrackingStatus(string $carrier, string $trackingNumber): ?array
    {
        if ($this->shippoApiKey === '' || !$this->available) {
            return null;
        }

        try {
            /** @var \Shippo_Track $result */
            $result = \Shippo_Track::get_status([
                'id'      => $trackingNumber,
                'carrier' => $carrier,
            ]);

            return $result ? (array) $result : null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Returns a human-readable French label for a Shippo status code.
     */
    public static function labelForStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PRE_TRANSIT'   => 'Prise en charge prévue',
            'TRANSIT'       => 'En transit',
            'DELIVERED'     => 'Livré',
            'RETURNED'      => 'Retourné',
            'FAILURE'       => 'Incident',
            'UNKNOWN'       => 'Inconnu',
            default         => ucfirst(strtolower($status)),
        };
    }

    /**
     * Returns a Font Awesome icon name for a Shippo status code.
     */
    public static function iconForStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PRE_TRANSIT'   => 'box',
            'TRANSIT'       => 'truck',
            'DELIVERED'     => 'check-circle',
            'RETURNED'      => 'undo',
            'FAILURE'       => 'exclamation-triangle',
            default         => 'circle-question',
        };
    }

    /**
     * Returns a CSS color for a Shippo status code.
     */
    public static function colorForStatus(string $status): string
    {
        return match (strtoupper($status)) {
            'PRE_TRANSIT'   => '#6366f1',
            'TRANSIT'       => '#f59e0b',
            'DELIVERED'     => '#10b981',
            'RETURNED'      => '#ef4444',
            'FAILURE'       => '#ef4444',
            default         => '#94a3b8',
        };
    }
}
