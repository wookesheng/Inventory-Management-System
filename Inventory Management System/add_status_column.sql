USE nehemiah_inventory;

-- Add status column to inventory_movements table
ALTER TABLE inventory_movements 
ADD COLUMN status ENUM('pending', 'confirmed', 'rejected') NOT NULL DEFAULT 'pending';

-- Update any existing NULL values to 'pending'
UPDATE inventory_movements SET status = 'pending' WHERE status IS NULL; 