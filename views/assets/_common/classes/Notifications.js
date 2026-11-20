import HttpClientBase from "./HttpClientBase.js";
import Notification from "./Notification.js";

export default class Notifications extends HttpClientBase {
    async list() {
        const response = await this.get("/notifications/list");
        if (!Array.isArray(response?.data)) throw new Error("A API nao retornou as notificacoes.");
        response.data = response.data.map((item) => Notification.fromAPI(item));
        return response;
    }

    async markRead(id) {
        return this.putForm("/notifications/:notification_id/read", {}, { notification_id: id });
    }

    async markAllRead() {
        return this.putForm("/notifications/read-all", {});
    }
}
