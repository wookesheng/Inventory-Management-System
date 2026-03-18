USE nehemiah_inventory;

-- Add delivery_type column if it doesn't exist
ALTER TABLE inventory_movements 
ADD COLUMN IF NOT EXISTS delivery_type ENUM('Delivery To Site', 'Delivery To Warehouse') NOT NULL 
AFTER movement_type; 