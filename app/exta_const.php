<?php

const EXTA_DEFAULT_REPO = "zamel";
const CRC32_POLYNOMIAL = 0x04C11DB7;

const EXTA_FW_SERVER_DNS_NAME = "extalife.cloud";
const EXTA_FW_SERVER_IP_REAL = "178.128.197.32";
const EXTA_FW_SERVER_IP_FAKE = "127.0.0.1";
const EXTA_FW_SRV_AUTH = [
    CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
];

const ZA_CHANGELOG_ID_BETA  = "1M0I3QOqeL2RoZb4omw1DWgC7mV2F8EVSuqzeLw_hLfQ";
const ZA_CHANGELOG_ID_RELEASE = "1oYifJndwc_fwIbaN8AaE1Tr0lWH5LXPAd3L1AbPAwpo";

const CHANGELOG_URL = "https://sheets.googleapis.com/v4/spreadsheets/%s/values/changelog%s?alt=json&key=%s";
