<?php

/*
|--------------------------------------------------------------------------
| Documents & Attachments
|--------------------------------------------------------------------------
|
| Patient records, encounters and clinical orders can carry uploaded files
| (scanned consents, referral letters, lab reports, imaging exports, ...).
|
| Files are never written to the public disk: every download is streamed
| through the API so the `documents.view` permission still applies.
|
*/

return [

    // Private disk used for stored files (see config/filesystems.php).
    'disk' => env('DOCUMENTS_DISK', 'local'),

    // Hard upload ceiling in kilobytes (default 25 MB).
    'max_kb' => (int) env('DOCUMENTS_MAX_KB', 25600),

    // Filings a document can be assigned to. Free text is not allowed so
    // reports and future imports stay consistent.
    'categories' => [
        'general',
        'consent',
        'referral',
        'lab_report',
        'imaging',
        'discharge_summary',
        'insurance',
        'identification',
        'correspondence',
        'legal',
        'other',
    ],

    // Allowed file extensions. Checked against the client filename; the
    // stored copy is always served as an attachment, never rendered inline.
    'extensions' => [
        'pdf',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'tif', 'tiff',
        'txt', 'csv', 'rtf',
        'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods',
        'dcm', 'xml', 'zip',
    ],

];
