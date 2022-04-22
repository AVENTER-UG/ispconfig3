#!/bin/bash

# Archive directories for deleted mailboxes.
delay_days=7

# Test if there is something to do... to avoid 'No such file or directory' from find later.
ls /var/vmail/*/[a-z0-9.-]*-deleted-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9] >/dev/null 2>&1
if [ $? != 0 ]; then
        exit 0;
fi

function remove_soft_deleted_mailbox {
        dir=$1

        echo "Purging $dir"
        rm -r "$dir"
}

function compress_soft_deleted_mailbox {
        dir=$1

        backupfile="${dir}.tar.bz2"

        # Test if backup file already exists
        if [ -f $backupfile ]; then
                # Skip
                echo "ERROR: Backupfile($backupfile) exists!" >&2
                continue
        fi

        echo "Compressing for $dir"
        tar cvfj "$backupfile" --remove-files "$dir" 2> >( grep -v "tar: Removing leading" >&2)
}

# List deleted mailboxs to archive
# -mtime +7 ===> Only mailboxes deleted more then 7 days ago
# Test that the last dir component matches e.g. xxx-deleted-20220101094242 (14 digits)
# command: xxx-`date "+%Y%m%d%H%M%S"`
find /var/vmail/*/[a-z0-9.-]*-deleted-[0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9][0-9]  -maxdepth 0 -type d -mtime +$delay_days | while read line; do
        # example $line: "/var/vmail/example.com/info-20220101094242"

        dir=$line

        # Uncomment the desired cleanup method below, or be creative and create your own.

        remove_soft_deleted_mailbox $dir
        #compress_soft_deleted_mailbox $dir

done
