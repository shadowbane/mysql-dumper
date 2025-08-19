import {toast} from "sonner";
import axios from "axios";
import {BackupLog} from "@/types/backup-log";
import {DataSource} from "@/types/datasource";

export const triggerBackup = async (dataSource: DataSource) => {
    try {
        const response = await axios.post(route('data-sources.single-backup', {
            data_source: dataSource.id,
        }));

        if (response.data.success) {
            toast.success("Backup started", {
                description: "Backup process has been initiated.",
            });
        } else {
            toast.error("Backup failed", {
                description: response.data.message || "Failed to start backup process.",
            });
        }
    } catch (error: any) {
        toast.error("Backup error", {
            description: error.response?.data?.message || "An error occurred while starting the backup.",
        });
    }
};

export const downloadBackup = async (backupLog: BackupLog) => {
    try {
        window.open(route('backup-logs.download', {backup_log: backupLog.id}), '_blank');
    } catch (error: any) {
        toast.error("Download error", {
            description: "An error occurred while downloading the backup file.",
        });
    }
};
