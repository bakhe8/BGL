SELECT 
    id,
    event_type,
    guarantee_number,
    LENGTH(snapshot_data) as snapshot_length,
    SUBSTR(snapshot_data, 1, 100) as snapshot_preview
FROM guarantee_timeline_events
WHERE guarantee_number = 'LG2123500014'
  AND event_type = 'import'
ORDER BY id DESC
LIMIT 1;
