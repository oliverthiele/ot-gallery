# Internal fields for CLI pre-processing — not shown in BE, not inferrable from TCA passthrough
CREATE TABLE tt_content (
    tx_otgallery_config_hash varchar(32) DEFAULT '' NOT NULL,
    tx_otgallery_processed_at int(11) DEFAULT 0 NOT NULL
);