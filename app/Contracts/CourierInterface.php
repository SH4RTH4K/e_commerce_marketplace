<?php

namespace App\Contracts;

interface CourierInterface
{
    /**
     * Create a shipment with the courier.
     * Returns an array with at minimum: tracking_code, consignment_id, status, raw_response.
     */
    public function createOrder(array $orderData): array;

    /**
     * Check the delivery status of a parcel by tracking code.
     */
    public function checkStatus(string $trackingCode): array;

    /**
     * Return available locations (cities/zones/areas) from the courier.
     * Not all couriers support this; return an empty array if unsupported.
     */
    public function getLocations(): array;
}
