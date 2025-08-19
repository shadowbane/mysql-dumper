import { toast } from "sonner";

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
