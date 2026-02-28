-- =====================================================
-- Migration: Add tags column to chat_conversations
-- =====================================================
-- This migration adds a JSON column to store tags for conversations
-- Used by the Automation Flows system for tag management actions
-- =====================================================

USE `wats_local`;

-- Check if column exists before adding
SET @col_exists = (
    SELECT COUNT(*) 
    FROM information_schema.COLUMNS 
    WHERE TABLE_SCHEMA = DATABASE() 
    AND TABLE_NAME = 'chat_conversations' 
    AND COLUMN_NAME = 'tags'
);

-- Add tags column if it doesn't exist
SET @query = IF(@col_exists = 0,
    'ALTER TABLE chat_conversations ADD COLUMN tags JSON DEFAULT NULL COMMENT "Array of tag IDs associated with this conversation" AFTER status',
    'SELECT "Column tags already exists" AS message'
);

PREPARE stmt FROM @query;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Verify the column was added
SELECT 
    COLUMN_NAME, 
    DATA_TYPE, 
    IS_NULLABLE, 
    COLUMN_DEFAULT,
    COLUMN_COMMENT
FROM information_schema.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
AND TABLE_NAME = 'chat_conversations' 
AND COLUMN_NAME = 'tags';
