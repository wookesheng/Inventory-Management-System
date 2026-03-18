USE nehemiah_inventory;

-- Add is_deleted column to inventory_items table
ALTER TABLE inventory_items 
ADD COLUMN is_deleted TINYINT(1) DEFAULT 0,
ADD COLUMN deleted_at TIMESTAMP NULL DEFAULT NULL; 