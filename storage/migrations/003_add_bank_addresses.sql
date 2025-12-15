-- Migration: Add Bank Address Information
-- Date: 2024-12-15
-- Description: Adds contact and address fields to banks table for dynamic letter generation

-- Add new columns with default values to preserve existing data
ALTER TABLE banks ADD COLUMN department VARCHAR(255) DEFAULT 'إدارة الضمانات';
ALTER TABLE banks ADD COLUMN address_line_1 VARCHAR(255) DEFAULT 'المقر الرئيسي';
ALTER TABLE banks ADD COLUMN address_line_2 VARCHAR(255) DEFAULT NULL;
ALTER TABLE banks ADD COLUMN contact_email VARCHAR(255) DEFAULT NULL;

-- Optional: Update some common banks with real data (examples)
-- Uncomment and modify as needed after importing your address file

-- UPDATE banks SET 
--     department = 'إدارة الضمانات',
--     address_line_1 = 'طريق الملك فهد، الرياض',
--     contact_email = 'guarantees@alahli.com'
-- WHERE official_name LIKE '%الأهلي%';

-- UPDATE banks SET 
--     department = 'إدارة الاعتمادات المستندية',
--     address_line_1 = 'شارع العليا، الرياض',
--     contact_email = 'lc@alrajhi.com'
-- WHERE official_name LIKE '%الراجحي%';
