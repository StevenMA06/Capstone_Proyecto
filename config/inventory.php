<?php

return [
    // Available units per equipment name, brand and model.
    'equipment_minimum' => max(0, (int) env('EQUIPMENT_MINIMUM_STOCK', 1)),
];
