USE nehemiah_inventory;

-- Add link column to notifications table if it doesn't exist
ALTER TABLE notifications 
ADD COLUMN link VARCHAR(255) NULL DEFAULT NULL AFTER message; 