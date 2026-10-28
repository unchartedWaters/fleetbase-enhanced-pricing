// Made by server/tests/ConsoleFixturesTest.php from the admin services and the example
// profile. Don't edit: run the PHP tests with WRITE_CONSOLE_FIXTURES=1 to make it again.
export default {
    "form": {
        "sections": [
            {
                "key": "general",
                "kind": "profile"
            },
            {
                "key": "products",
                "kind": "product",
                "list": true,
                "max": 50
            },
            {
                "key": "attributes",
                "kind": "attribute",
                "list": true,
                "max": 20
            },
            {
                "key": "entity_types",
                "kind": "entity_type",
                "list": true,
                "max": 20
            },
            {
                "key": "rate_tables",
                "kind": "rate_table",
                "list": true,
                "max": 10
            },
            {
                "key": "service_rates",
                "kind": "service_rate",
                "list": true,
                "max": 20
            },
            {
                "key": "fees",
                "kind": "fee",
                "list": true,
                "max": 50,
                "ordered": true
            },
            {
                "key": "subtotal_fees",
                "kind": "subtotal_fee",
                "list": true,
                "max": 50,
                "ordered": true
            },
            {
                "key": "manual_charges",
                "kind": "manual_charge",
                "list": true,
                "max": 20
            },
            {
                "key": "holiday_lists",
                "kind": "holiday_list",
                "map": true,
                "max": 10
            }
        ],
        "kinds": {
            "profile": {
                "fields": [
                    {
                        "name": "currency",
                        "type": "currency",
                        "fixed": true
                    },
                    {
                        "name": "scheduling_timezone",
                        "type": "timezone"
                    },
                    {
                        "name": "distance_unit",
                        "type": "enum",
                        "options": [
                            "km",
                            "mi"
                        ],
                        "fixed": true
                    },
                    {
                        "name": "default_entity_type",
                        "type": "ref",
                        "ref": "entity_types"
                    }
                ]
            },
            "product": {
                "fields": [
                    {
                        "name": "code",
                        "type": "code"
                    },
                    {
                        "name": "name",
                        "type": "text"
                    }
                ]
            },
            "attribute": {
                "discriminator": "type",
                "fields": [
                    {
                        "name": "key",
                        "type": "key"
                    },
                    {
                        "name": "name",
                        "type": "text"
                    },
                    {
                        "name": "scope",
                        "type": "enum",
                        "options": [
                            "order",
                            "entity"
                        ]
                    },
                    {
                        "name": "required",
                        "type": "boolean",
                        "optional": true,
                        "default": false
                    },
                    {
                        "name": "required_for",
                        "type": "refs",
                        "ref": "service_rates",
                        "max": 20,
                        "optional": true,
                        "default": []
                    }
                ],
                "variants": {
                    "boolean": [],
                    "choice": [
                        {
                            "name": "choices",
                            "type": "list",
                            "of": "choice",
                            "min": 1,
                            "max": 50
                        }
                    ],
                    "number": [
                        {
                            "name": "unit",
                            "type": "text",
                            "nullable": true,
                            "optional": true,
                            "default": null
                        },
                        {
                            "name": "maximum",
                            "type": "number",
                            "optional": true,
                            "default": "1000000"
                        }
                    ]
                }
            },
            "choice": {
                "fields": [
                    {
                        "name": "key",
                        "type": "key"
                    },
                    {
                        "name": "name",
                        "type": "text"
                    }
                ]
            },
            "entity_type": {
                "fields": [
                    {
                        "name": "key",
                        "type": "key"
                    },
                    {
                        "name": "name",
                        "type": "text"
                    },
                    {
                        "name": "own_price",
                        "type": "object",
                        "of": "own_price",
                        "nullable": true,
                        "optional": true,
                        "default": null
                    }
                ]
            },
            "own_price": {
                "fields": [
                    {
                        "name": "column",
                        "type": "ref",
                        "ref": "money_columns"
                    },
                    {
                        "name": "product",
                        "type": "ref",
                        "ref": "products"
                    }
                ]
            },
            "rate_table": {
                "fields": [
                    {
                        "name": "key",
                        "type": "key"
                    },
                    {
                        "name": "columns",
                        "type": "list",
                        "of": "column",
                        "min": 1,
                        "max": 30
                    },
                    {
                        "name": "row_key",
                        "type": "refs",
                        "ref": "columns",
                        "min": 1,
                        "max": 5
                    }
                ]
            },
            "column": {
                "fields": [
                    {
                        "name": "key",
                        "type": "key"
                    },
                    {
                        "name": "type",
                        "type": "enum",
                        "options": [
                            "text",
                            "money",
                            "integer",
                            "number"
                        ]
                    },
                    {
                        "name": "required",
                        "type": "boolean",
                        "optional": true,
                        "default": false
                    }
                ]
            },
            "service_rate": {
                "discriminator": "method",
                "fields": [
                    {
                        "name": "key",
                        "type": "key"
                    },
                    {
                        "name": "name",
                        "type": "text"
                    },
                    {
                        "name": "product",
                        "type": "ref",
                        "ref": "products"
                    },
                    {
                        "name": "rate_table",
                        "type": "ref",
                        "ref": "rate_tables",
                        "nullable": true,
                        "optional": true,
                        "default": null
                    },
                    {
                        "name": "line_name",
                        "type": "choice",
                        "choices": {
                            "text": {
                                "type": "text"
                            },
                            "column": {
                                "type": "ref",
                                "ref": "text_columns"
                            }
                        }
                    },
                    {
                        "name": "minimum",
                        "type": "money",
                        "nullable": true,
                        "optional": true,
                        "default": null
                    },
                    {
                        "name": "enabled",
                        "type": "boolean",
                        "optional": true,
                        "default": true
                    }
                ],
                "variants": {
                    "tiers": [
                        {
                            "name": "tiers",
                            "type": "list",
                            "of": "tier",
                            "min": 1,
                            "max": 10
                        }
                    ],
                    "per_meter": [
                        {
                            "name": "per_meter",
                            "type": "object",
                            "of": "per_meter"
                        }
                    ],
                    "fixed_rate": [
                        {
                            "name": "fixed_rate",
                            "type": "object",
                            "of": "fixed_rate"
                        }
                    ]
                }
            },
            "tier": {
                "fields": [
                    {
                        "name": "up_to",
                        "type": "choice",
                        "nullable": true,
                        "choices": {
                            "value": {
                                "type": "integer",
                                "min": 1,
                                "max": 100000
                            },
                            "column": {
                                "type": "ref",
                                "ref": "integer_columns"
                            }
                        }
                    },
                    {
                        "name": "price",
                        "type": "choice",
                        "choices": {
                            "value": {
                                "type": "money"
                            },
                            "column": {
                                "type": "ref",
                                "ref": "money_columns"
                            }
                        }
                    }
                ]
            },
            "per_meter": {
                "fields": [
                    {
                        "name": "rate",
                        "type": "money"
                    },
                    {
                        "name": "base_fee",
                        "type": "money",
                        "optional": true,
                        "default": 0
                    },
                    {
                        "name": "minimum_distance",
                        "type": "number",
                        "optional": true,
                        "default": "0"
                    },
                    {
                        "name": "maximum_distance",
                        "type": "number",
                        "max": "20000"
                    },
                    {
                        "name": "rounding",
                        "type": "enum",
                        "options": [
                            "up",
                            "nearest",
                            "none"
                        ]
                    }
                ]
            },
            "fixed_rate": {
                "fields": [
                    {
                        "name": "amount",
                        "type": "choice",
                        "choices": {
                            "value": {
                                "type": "money"
                            },
                            "column": {
                                "type": "ref",
                                "ref": "money_columns"
                            }
                        }
                    }
                ]
            },
            "fee": {
                "discriminator": "kind",
                "fields": [
                    {
                        "name": "key",
                        "type": "key"
                    },
                    {
                        "name": "name",
                        "type": "text"
                    },
                    {
                        "name": "product",
                        "type": "ref",
                        "ref": "products"
                    },
                    {
                        "name": "applies_to",
                        "type": "refs",
                        "ref": "products",
                        "min": 1,
                        "max": 50
                    },
                    {
                        "name": "minimum",
                        "type": "money",
                        "nullable": true,
                        "optional": true,
                        "default": null
                    },
                    {
                        "name": "maximum",
                        "type": "money",
                        "nullable": true,
                        "optional": true,
                        "default": null
                    },
                    {
                        "name": "effective_from",
                        "type": "date",
                        "nullable": true,
                        "optional": true,
                        "default": null
                    },
                    {
                        "name": "effective_until",
                        "type": "date",
                        "nullable": true,
                        "optional": true,
                        "default": null
                    },
                    {
                        "name": "enabled",
                        "type": "boolean",
                        "optional": true,
                        "default": true
                    }
                ],
                "variants": {
                    "peak_hours": [
                        {
                            "name": "charge",
                            "type": "enum",
                            "options": [
                                "per_entity",
                                "per_order"
                            ]
                        },
                        {
                            "name": "amount",
                            "type": "money",
                            "nullable": true
                        },
                        {
                            "name": "if_missing",
                            "type": "enum",
                            "options": [
                                "stop",
                                "skip"
                            ],
                            "optional": true,
                            "default": "stop"
                        },
                        {
                            "name": "window",
                            "type": "object",
                            "of": "window",
                            "nullable": true,
                            "optional": true,
                            "default": null
                        },
                        {
                            "name": "weekdays",
                            "type": "weekdays",
                            "nullable": true,
                            "optional": true,
                            "default": null
                        },
                        {
                            "name": "holidays",
                            "type": "ref",
                            "ref": "holiday_lists",
                            "nullable": true,
                            "optional": true,
                            "default": null
                        }
                    ],
                    "parcel": [
                        {
                            "name": "charge",
                            "type": "enum",
                            "options": [
                                "per_entity",
                                "per_order",
                                "per_unit"
                            ]
                        },
                        {
                            "name": "basis",
                            "type": "enum",
                            "options": [
                                "weight",
                                "volume"
                            ]
                        },
                        {
                            "name": "measure",
                            "type": "enum",
                            "options": [
                                "actual",
                                "dimensional",
                                "greater"
                            ],
                            "when": {
                                "basis": [
                                    "weight"
                                ]
                            }
                        },
                        {
                            "name": "divisor",
                            "type": "number",
                            "when": {
                                "measure": [
                                    "dimensional",
                                    "greater"
                                ]
                            }
                        },
                        {
                            "name": "unit",
                            "type": "enum",
                            "options": [
                                "g",
                                "oz",
                                "lb",
                                "kg",
                                "cm3",
                                "m3",
                                "in3",
                                "ft3"
                            ]
                        },
                        {
                            "name": "scope",
                            "type": "enum",
                            "options": [
                                "entity",
                                "order"
                            ]
                        },
                        {
                            "name": "thresholds",
                            "type": "list",
                            "of": "threshold",
                            "min": 1,
                            "max": 10
                        }
                    ],
                    "additional": [
                        {
                            "name": "charge",
                            "type": "enum",
                            "options": [
                                "per_entity",
                                "per_order",
                                "per_unit"
                            ]
                        },
                        {
                            "name": "amount",
                            "type": "money",
                            "nullable": true
                        },
                        {
                            "name": "if_missing",
                            "type": "enum",
                            "options": [
                                "stop",
                                "skip"
                            ],
                            "optional": true,
                            "default": "stop"
                        },
                        {
                            "name": "attribute",
                            "type": "ref",
                            "ref": "attributes"
                        },
                        {
                            "name": "equals",
                            "type": "ref",
                            "ref": "choices",
                            "nullable": true,
                            "optional": true,
                            "default": null
                        }
                    ]
                }
            },
            "window": {
                "fields": [
                    {
                        "name": "from",
                        "type": "time"
                    },
                    {
                        "name": "to",
                        "type": "time"
                    }
                ]
            },
            "threshold": {
                "fields": [
                    {
                        "name": "over",
                        "type": "number"
                    },
                    {
                        "name": "amount",
                        "type": "money"
                    }
                ]
            },
            "subtotal_fee": {
                "discriminator": "mode",
                "fields": [
                    {
                        "name": "key",
                        "type": "key"
                    },
                    {
                        "name": "name",
                        "type": "text"
                    },
                    {
                        "name": "product",
                        "type": "ref",
                        "ref": "products"
                    },
                    {
                        "name": "applies_to",
                        "type": "refs",
                        "ref": "products",
                        "min": 1,
                        "max": 50
                    },
                    {
                        "name": "requires",
                        "type": "refs",
                        "ref": "products",
                        "max": 50,
                        "optional": true,
                        "default": []
                    },
                    {
                        "name": "skipped_for",
                        "type": "refs",
                        "ref": "products",
                        "max": 50,
                        "optional": true,
                        "default": []
                    },
                    {
                        "name": "minimum",
                        "type": "money",
                        "nullable": true,
                        "optional": true,
                        "default": null
                    },
                    {
                        "name": "maximum",
                        "type": "money",
                        "nullable": true,
                        "optional": true,
                        "default": null
                    },
                    {
                        "name": "effective_from",
                        "type": "date",
                        "nullable": true,
                        "optional": true,
                        "default": null
                    },
                    {
                        "name": "effective_until",
                        "type": "date",
                        "nullable": true,
                        "optional": true,
                        "default": null
                    },
                    {
                        "name": "enabled",
                        "type": "boolean",
                        "optional": true,
                        "default": true
                    }
                ],
                "variants": {
                    "percentage": [
                        {
                            "name": "percentage",
                            "type": "percent",
                            "nullable": true,
                            "optional": true,
                            "default": null
                        },
                        {
                            "name": "charge",
                            "type": "enum",
                            "options": [
                                "per_order"
                            ]
                        },
                        {
                            "name": "if_missing",
                            "type": "enum",
                            "options": [
                                "stop",
                                "skip"
                            ],
                            "optional": true,
                            "default": "stop"
                        }
                    ],
                    "flat": [
                        {
                            "name": "amount",
                            "type": "money",
                            "nullable": true,
                            "optional": true,
                            "default": null
                        },
                        {
                            "name": "charge",
                            "type": "enum",
                            "options": [
                                "per_order",
                                "per_line",
                                "per_entity"
                            ]
                        },
                        {
                            "name": "if_missing",
                            "type": "enum",
                            "options": [
                                "stop",
                                "skip"
                            ],
                            "optional": true,
                            "default": "stop"
                        }
                    ]
                }
            },
            "manual_charge": {
                "discriminator": null,
                "fields": [
                    {
                        "name": "key",
                        "type": "key"
                    },
                    {
                        "name": "name",
                        "type": "text"
                    },
                    {
                        "name": "product",
                        "type": "ref",
                        "ref": "products"
                    },
                    {
                        "name": "offered",
                        "type": "boolean",
                        "optional": true,
                        "default": true
                    },
                    {
                        "name": "effective_from",
                        "type": "date",
                        "nullable": true,
                        "optional": true,
                        "default": null
                    },
                    {
                        "name": "effective_until",
                        "type": "date",
                        "nullable": true,
                        "optional": true,
                        "default": null
                    }
                ],
                "variants": {
                    "amount": [
                        {
                            "name": "amount",
                            "type": "money"
                        },
                        {
                            "name": "quantity",
                            "type": "object",
                            "of": "quantity",
                            "nullable": true,
                            "optional": true,
                            "default": null
                        }
                    ],
                    "entered": [
                        {
                            "name": "entered",
                            "type": "object",
                            "of": "entered"
                        }
                    ]
                }
            },
            "entered": {
                "fields": [
                    {
                        "name": "minimum",
                        "type": "money"
                    },
                    {
                        "name": "maximum",
                        "type": "money"
                    }
                ]
            },
            "quantity": {
                "fields": [
                    {
                        "name": "unit",
                        "type": "text"
                    },
                    {
                        "name": "step",
                        "type": "number"
                    },
                    {
                        "name": "maximum",
                        "type": "number"
                    }
                ]
            },
            "holiday_list": {
                "fields": [
                    {
                        "name": "dates",
                        "type": "dates",
                        "max": 400
                    }
                ]
            }
        }
    },
    "profiles": {
        "profiles": [
            {
                "key": "main",
                "name": "Main",
                "currency": "USD",
                "distance_unit": "km",
                "current_version": 2,
                "saved_at": "2026-11-02T12:00:00+00:00",
                "saved_by": {
                    "uuid": "00000000-0000-4000-8000-000000000001",
                    "name": "User a"
                },
                "customers": 2
            }
        ]
    },
    "profile": {
        "key": "main",
        "name": "Main",
        "currency": "USD",
        "distance_unit": "km",
        "current_version": 2,
        "version": {
            "number": 2,
            "saved_at": "2026-11-02T12:00:00+00:00",
            "saved_by": {
                "uuid": "00000000-0000-4000-8000-000000000001",
                "name": "User a"
            },
            "definition": {
                "currency": "USD",
                "scheduling_timezone": "UTC",
                "distance_unit": "km",
                "products": [
                    {
                        "code": "TIERED",
                        "name": "Delivery"
                    },
                    {
                        "code": "SEPARATE",
                        "name": "Separate entity type"
                    },
                    {
                        "code": "DISTANCE",
                        "name": "Distance delivery"
                    },
                    {
                        "code": "ADDITIONAL",
                        "name": "Additional fee"
                    },
                    {
                        "code": "PARCEL",
                        "name": "Parcel fee"
                    },
                    {
                        "code": "PEAK",
                        "name": "Peak hours fee"
                    },
                    {
                        "code": "PERCENT",
                        "name": "Percentage subtotal fee"
                    },
                    {
                        "code": "FLAT_A",
                        "name": "Flat subtotal fee A"
                    },
                    {
                        "code": "FLAT_B",
                        "name": "Flat subtotal fee B"
                    },
                    {
                        "code": "HOURLY",
                        "name": "Hourly charge"
                    },
                    {
                        "code": "ENTERED",
                        "name": "Entered charge"
                    }
                ],
                "attributes": [
                    {
                        "key": "extra",
                        "name": "Extra handling",
                        "scope": "order",
                        "type": "boolean",
                        "required": false
                    }
                ],
                "entity_types": [
                    {
                        "key": "standard",
                        "name": "Standard"
                    },
                    {
                        "key": "separate",
                        "name": "Separate entity type",
                        "own_price": {
                            "column": "separate_price",
                            "product": "SEPARATE"
                        }
                    }
                ],
                "default_entity_type": "standard",
                "rate_tables": [
                    {
                        "key": "rows",
                        "columns": [
                            {
                                "key": "code",
                                "type": "text",
                                "required": true
                            },
                            {
                                "key": "first",
                                "type": "money",
                                "required": true
                            },
                            {
                                "key": "second",
                                "type": "money",
                                "required": true
                            },
                            {
                                "key": "third",
                                "type": "money",
                                "required": true
                            },
                            {
                                "key": "separate_price",
                                "type": "money",
                                "required": false
                            }
                        ],
                        "row_key": [
                            "code"
                        ]
                    }
                ],
                "service_rates": [
                    {
                        "key": "tiered",
                        "name": "Tiered delivery",
                        "product": "TIERED",
                        "rate_table": "rows",
                        "line_name": {
                            "text": "Tiered delivery"
                        },
                        "method": "tiers",
                        "tiers": [
                            {
                                "up_to": {
                                    "value": 2
                                },
                                "price": {
                                    "column": "first"
                                }
                            },
                            {
                                "up_to": {
                                    "value": 5
                                },
                                "price": {
                                    "column": "second"
                                }
                            },
                            {
                                "up_to": null,
                                "price": {
                                    "column": "third"
                                }
                            }
                        ],
                        "minimum": null,
                        "enabled": true
                    },
                    {
                        "key": "distance",
                        "name": "Distance delivery",
                        "product": "DISTANCE",
                        "rate_table": null,
                        "line_name": {
                            "text": "Distance"
                        },
                        "method": "per_meter",
                        "per_meter": {
                            "rate": 210,
                            "base_fee": 1400,
                            "minimum_distance": 10,
                            "maximum_distance": 1000,
                            "rounding": "up"
                        },
                        "minimum": null,
                        "enabled": true
                    }
                ],
                "fees": [
                    {
                        "key": "extra_handling",
                        "kind": "additional",
                        "name": "Additional fee",
                        "product": "ADDITIONAL",
                        "applies_to": [
                            "TIERED",
                            "SEPARATE",
                            "DISTANCE"
                        ],
                        "charge": "per_order",
                        "amount": 1600,
                        "if_missing": "stop",
                        "minimum": null,
                        "maximum": null,
                        "effective_from": null,
                        "effective_until": null,
                        "enabled": true,
                        "attribute": "extra",
                        "equals": null
                    },
                    {
                        "key": "heavy",
                        "kind": "parcel",
                        "name": "Parcel fee",
                        "product": "PARCEL",
                        "applies_to": [
                            "TIERED",
                            "SEPARATE",
                            "DISTANCE"
                        ],
                        "charge": "per_unit",
                        "minimum": null,
                        "maximum": null,
                        "effective_from": null,
                        "effective_until": null,
                        "enabled": true,
                        "basis": "weight",
                        "measure": "actual",
                        "divisor": null,
                        "unit": "kg",
                        "scope": "entity",
                        "thresholds": [
                            {
                                "over": 30,
                                "amount": 40
                            }
                        ]
                    },
                    {
                        "key": "peak",
                        "kind": "peak_hours",
                        "name": "Peak hours fee",
                        "product": "PEAK",
                        "applies_to": [
                            "TIERED",
                            "SEPARATE",
                            "DISTANCE"
                        ],
                        "charge": "per_order",
                        "amount": 2200,
                        "if_missing": "stop",
                        "minimum": null,
                        "maximum": null,
                        "effective_from": null,
                        "effective_until": null,
                        "enabled": true,
                        "window": null,
                        "weekdays": [
                            6,
                            7
                        ],
                        "holidays": null
                    }
                ],
                "subtotal_fees": [
                    {
                        "key": "percent",
                        "name": "Percentage subtotal fee",
                        "product": "PERCENT",
                        "applies_to": [
                            "TIERED",
                            "SEPARATE",
                            "ADDITIONAL",
                            "PARCEL",
                            "PEAK"
                        ],
                        "requires": [],
                        "skipped_for": [],
                        "mode": "percentage",
                        "percentage": 1100,
                        "amount": null,
                        "charge": "per_order",
                        "if_missing": "stop",
                        "minimum": null,
                        "maximum": null,
                        "effective_from": null,
                        "effective_until": null,
                        "enabled": true
                    },
                    {
                        "key": "flat_a",
                        "name": "Flat subtotal fee A",
                        "product": "FLAT_A",
                        "applies_to": [
                            "ADDITIONAL"
                        ],
                        "requires": [],
                        "skipped_for": [
                            "DISTANCE"
                        ],
                        "mode": "flat",
                        "percentage": null,
                        "amount": 400,
                        "charge": "per_order",
                        "if_missing": "stop",
                        "minimum": null,
                        "maximum": null,
                        "effective_from": null,
                        "effective_until": null,
                        "enabled": true
                    },
                    {
                        "key": "flat_b",
                        "name": "Flat subtotal fee B",
                        "product": "FLAT_B",
                        "applies_to": [
                            "PARCEL"
                        ],
                        "requires": [
                            "ADDITIONAL"
                        ],
                        "skipped_for": [],
                        "mode": "flat",
                        "percentage": null,
                        "amount": 600,
                        "charge": "per_order",
                        "if_missing": "stop",
                        "minimum": null,
                        "maximum": null,
                        "effective_from": null,
                        "effective_until": null,
                        "enabled": true
                    }
                ],
                "manual_charges": [
                    {
                        "key": "hourly",
                        "name": "Hourly charge",
                        "product": "HOURLY",
                        "amount": 4200,
                        "entered": null,
                        "quantity": {
                            "unit": "h",
                            "step": "0.25",
                            "maximum": "24"
                        },
                        "offered": true,
                        "effective_from": null,
                        "effective_until": null
                    },
                    {
                        "key": "entered",
                        "name": "Entered charge",
                        "product": "ENTERED",
                        "amount": null,
                        "entered": {
                            "minimum": 800,
                            "maximum": 15000
                        },
                        "quantity": null,
                        "offered": true,
                        "effective_from": null,
                        "effective_until": null
                    }
                ],
                "holiday_lists": []
            }
        },
        "versions": [
            {
                "number": 2,
                "saved_at": "2026-11-02T12:00:00+00:00",
                "saved_by": {
                    "uuid": "00000000-0000-4000-8000-000000000001",
                    "name": "User a"
                }
            },
            {
                "number": 1,
                "saved_at": "2026-11-02T12:00:00+00:00",
                "saved_by": null
            }
        ]
    },
    "schedule": {
        "today": "2026-11-02",
        "subtotal_fees": [
            {
                "key": "percent",
                "name": "Percentage subtotal fee",
                "mode": "percentage",
                "path": "subtotal_fees.percent.percentage",
                "profile_value": 1100,
                "current": {
                    "value": 1100,
                    "effective_from": null
                },
                "upcoming": [
                    {
                        "uuid": "00000000-0000-4000-8000-000000000002",
                        "path": "subtotal_fees.percent.percentage",
                        "value": 1300,
                        "effective_from": "2026-12-01",
                        "state": "upcoming",
                        "added_at": "2026-11-02T12:00:00+00:00",
                        "added_by": {
                            "uuid": "00000000-0000-4000-8000-000000000001",
                            "name": "User a"
                        },
                        "cancelled_at": null,
                        "cancelled_by": null,
                        "problems": null
                    }
                ],
                "overridden_by": [
                    {
                        "uuid": "00000000-0000-4000-8000-000000000003",
                        "name": "Customer a 1",
                        "value": 900
                    }
                ]
            },
            {
                "key": "flat_a",
                "name": "Flat subtotal fee A",
                "mode": "flat",
                "path": "subtotal_fees.flat_a.amount",
                "profile_value": 400,
                "current": {
                    "value": 400,
                    "effective_from": null
                },
                "upcoming": [],
                "overridden_by": []
            },
            {
                "key": "flat_b",
                "name": "Flat subtotal fee B",
                "mode": "flat",
                "path": "subtotal_fees.flat_b.amount",
                "profile_value": 600,
                "current": {
                    "value": 600,
                    "effective_from": null
                },
                "upcoming": [],
                "overridden_by": []
            }
        ],
        "changes": [
            {
                "uuid": "00000000-0000-4000-8000-000000000002",
                "path": "subtotal_fees.percent.percentage",
                "value": 1300,
                "effective_from": "2026-12-01",
                "state": "upcoming",
                "added_at": "2026-11-02T12:00:00+00:00",
                "added_by": {
                    "uuid": "00000000-0000-4000-8000-000000000001",
                    "name": "User a"
                },
                "cancelled_at": null,
                "cancelled_by": null,
                "problems": null
            }
        ]
    },
    "customers": {
        "customers": [
            {
                "uuid": "00000000-0000-4000-8000-000000000003",
                "customer_uuid": "00000000-0000-4000-8000-000000000004",
                "name": "Customer a 1",
                "overrides": 2,
                "cards": 1,
                "checked_version": 2,
                "problems": null
            },
            {
                "uuid": "00000000-0000-4000-8000-000000000005",
                "customer_uuid": "00000000-0000-4000-8000-000000000006",
                "name": "Customer a 2",
                "overrides": 0,
                "cards": 0,
                "checked_version": 2,
                "problems": null
            }
        ],
        "total": 2,
        "page": 1,
        "per_page": 50
    },
    "customer": {
        "uuid": "00000000-0000-4000-8000-000000000003",
        "customer_uuid": "00000000-0000-4000-8000-000000000004",
        "name": "Customer a 1",
        "profile": {
            "key": "main",
            "name": "Main",
            "currency": "USD",
            "current_version": 2,
            "columns": {
                "rows": {
                    "code": "text",
                    "first": "money",
                    "second": "money",
                    "third": "money",
                    "separate_price": "money"
                }
            }
        },
        "checked_version": 2,
        "problems": null,
        "overrides": [
            {
                "path": "fees.heavy.minimum",
                "value": 500,
                "default": null,
                "change": null
            },
            {
                "path": "subtotal_fees.percent.percentage",
                "value": 900,
                "default": 1100,
                "change": null
            }
        ],
        "cards": [
            {
                "uuid": "00000000-0000-4000-8000-000000000007",
                "label": "Main",
                "rows": [
                    {
                        "uuid": "00000000-0000-4000-8000-000000000008",
                        "table_key": "rows",
                        "key_values": [
                            "R1"
                        ],
                        "values": {
                            "code": "R1",
                            "first": 2700,
                            "second": 1800,
                            "third": 1200,
                            "separate_price": 900
                        },
                        "checked_version": 2,
                        "problems": null
                    },
                    {
                        "uuid": "00000000-0000-4000-8000-000000000009",
                        "table_key": "rows",
                        "key_values": [
                            "R2"
                        ],
                        "values": {
                            "code": "R2",
                            "first": 2705,
                            "second": 1800,
                            "third": 1200,
                            "separate_price": 900
                        },
                        "checked_version": 2,
                        "problems": null
                    }
                ]
            }
        ]
    },
    "preview": {
        "quote": {
            "currency": "USD",
            "total": 12554,
            "lines": [
                {
                    "product": "TIERED",
                    "name": "Tiered delivery",
                    "quantity": 2,
                    "unit_amount": 2700,
                    "amount": 5400,
                    "sources": [
                        "service_rate:tiered"
                    ]
                },
                {
                    "product": "SEPARATE",
                    "name": "Separate entity type",
                    "quantity": 1,
                    "unit_amount": 900,
                    "amount": 900,
                    "sources": [
                        "entity_type:separate"
                    ]
                },
                {
                    "product": "ADDITIONAL",
                    "name": "Additional fee",
                    "quantity": 1,
                    "unit_amount": 1600,
                    "amount": 1600,
                    "sources": [
                        "fee:extra_handling"
                    ]
                },
                {
                    "product": "PARCEL",
                    "name": "Parcel fee",
                    "quantity": 1,
                    "unit_amount": 500,
                    "amount": 500,
                    "sources": [
                        "fee:heavy"
                    ]
                },
                {
                    "product": "PEAK",
                    "name": "Peak hours fee",
                    "quantity": 1,
                    "unit_amount": 2200,
                    "amount": 2200,
                    "sources": [
                        "fee:peak"
                    ]
                },
                {
                    "product": "PERCENT",
                    "name": "Percentage subtotal fee",
                    "quantity": 1,
                    "unit_amount": 954,
                    "amount": 954,
                    "sources": [
                        "subtotal_fee:percent"
                    ]
                },
                {
                    "product": "FLAT_A",
                    "name": "Flat subtotal fee A",
                    "quantity": 1,
                    "unit_amount": 400,
                    "amount": 400,
                    "sources": [
                        "subtotal_fee:flat_a"
                    ]
                },
                {
                    "product": "FLAT_B",
                    "name": "Flat subtotal fee B",
                    "quantity": 1,
                    "unit_amount": 600,
                    "amount": 600,
                    "sources": [
                        "subtotal_fee:flat_b"
                    ]
                }
            ],
            "notes": [],
            "meta": {
                "timezone": "UTC",
                "pricing_date": "2027-03-06",
                "service_rate": "tiered",
                "overrides": {
                    "subtotal_fees.percent.percentage": 900,
                    "fees.heavy.minimum": 500
                },
                "changes": []
            }
        },
        "failed": null,
        "stale": null,
        "skipped_changes": []
    },
    "failed": {
        "failed": {
            "key": "enhanced-pricing.pricing.no-entities",
            "params": {
                "customer": "Customer a 1",
                "card": "Main",
                "row": "R1"
            }
        },
        "quote": null,
        "stale": null,
        "skipped_changes": []
    },
    "refused": {
        "invalid": {
            "refused": "invalid",
            "problems": [
                {
                    "path": "products.1.code",
                    "key": "enhanced-pricing.profile.wrong-type",
                    "params": {
                        "expected": "code"
                    }
                },
                {
                    "path": "fees.0.minimum",
                    "key": "enhanced-pricing.profile.out-of-range",
                    "params": {
                        "min": 0,
                        "max": 100000000
                    }
                }
            ]
        },
        "outdated": {
            "refused": "outdated",
            "problems": [
                {
                    "path": "",
                    "key": "enhanced-pricing.store.version-outdated",
                    "params": {
                        "current": 2
                    }
                }
            ],
            "newer": {
                "number": 2,
                "saved_at": "2026-11-02T12:00:00+00:00",
                "saved_by": {
                    "uuid": "00000000-0000-4000-8000-000000000001",
                    "name": "User a"
                }
            }
        },
        "stale": {
            "refused": "stale",
            "problems": [
                {
                    "path": "",
                    "key": "enhanced-pricing.store.stale-entries",
                    "params": {
                        "count": 1
                    }
                }
            ],
            "stale": [
                {
                    "kind": "customer",
                    "uuid": "00000000-0000-4000-8000-000000000003",
                    "label": "Customer a 1",
                    "problems": [
                        {
                            "path": "fees.heavy.minimum",
                            "key": "enhanced-pricing.profile.unknown-override",
                            "params": {
                                "path": "fees.heavy.minimum"
                            }
                        }
                    ]
                }
            ]
        }
    }
};
