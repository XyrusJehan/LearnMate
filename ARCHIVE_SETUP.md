# Group Archive Functionality Setup

This document explains how to set up the group archive functionality for LearnMate.

## Database Setup

1. Run the SQL script `update_groups_table.sql` to add the necessary fields to the groups table:

```sql
-- Add missing fields to groups table
ALTER TABLE `groups` 
ADD COLUMN `description` TEXT NULL AFTER `name`,
ADD COLUMN `passcode` VARCHAR(255) NULL AFTER `privacy`,
ADD COLUMN `is_archived` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1 = archived, 0 = active' AFTER `created_by`;

-- Update existing groups to have default values
UPDATE `groups` SET `description` = '' WHERE `description` IS NULL;
UPDATE `groups` SET `is_archived` = 0 WHERE `is_archived` IS NULL;
```

## Files Added/Modified

### New Files Created:
1. `update_groups_table.sql` - Database migration script
2. `archive_group.php` - AJAX handler for archiving groups
3. `archived_groups.php` - Page to view and restore archived groups
4. `ARCHIVE_SETUP.md` - This setup guide

### Files Modified:
1. `teacher_group.php` - Added archive button and filtered out archived groups
2. `student_group.php` - Added archive button and filtered out archived groups
3. `settings.php` - Added link to archived groups page

## Features

### Archive Functionality:
- **Archive Button**: Added to the three-dot menu on group cards (for admin users only)
- **AJAX Archiving**: Groups are archived via AJAX with loading states and success messages
- **Filtered Display**: Archived groups are automatically hidden from the main groups page
- **Restore Functionality**: Archived groups can be restored from Settings > Archived Groups

### User Experience:
- **Confirmation Dialog**: Users must confirm before archiving a group
- **Loading States**: Visual feedback during the archiving process
- **Success Messages**: Clear feedback when groups are archived successfully
- **Error Handling**: Proper error messages if archiving fails

### Access Control:
- **Admin Only**: Only group admins can archive groups
- **Permission Verification**: Server-side verification of admin status
- **Secure AJAX**: Proper authentication checks on all archive operations

## Usage

### For Teachers/Students:
1. Go to the Groups page
2. Find a group you're an admin of
3. Click the three-dot menu (⋮) on the group card
4. Select "Archive Group"
5. Confirm the action
6. The group will be archived and hidden from the main view

### To Restore Archived Groups:
1. Go to Settings
2. Click "Archived Groups"
3. Find the group you want to restore
4. Click the three-dot menu and select "Restore"
5. The group will be restored and visible again

## Technical Details

### Database Schema Changes:
- Added `description` field (TEXT, nullable)
- Added `passcode` field (VARCHAR(255), nullable) 
- Added `is_archived` field (TINYINT(1), default 0)

### Security Features:
- Session-based authentication
- Admin permission verification
- SQL injection prevention with prepared statements
- CSRF protection through session validation

### Performance Considerations:
- Archived groups are filtered out at the database level
- Efficient queries with proper indexing
- Minimal AJAX payload size 