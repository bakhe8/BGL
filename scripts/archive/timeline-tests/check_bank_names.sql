SELECT 
    id,
    name,
    officialName,
    normalized_name
FROM banks
WHERE name LIKE '%SNB%' OR officialName LIKE '%عرب%' OR name LIKE '%Arab%'
LIMIT 5;
