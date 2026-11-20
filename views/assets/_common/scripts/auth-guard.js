import SessionStorage from "./storage.js";

export default class AuthGuard {
    // expectedTypeId aceita um tipo (ex.: 1) ou uma lista de tipos (ex.: [3, 4]).
    constructor(redirectUrl = "../public/login.html", scope = "user", expectedTypeId = null) {
        this.storage = new SessionStorage(scope);
        this.redirectUrl = redirectUrl;
        this.expectedTypeId = expectedTypeId;
    }

    isAuthenticated() {
        const token = this.storage.getToken();
        const user = this.storage.getUser();

        if (!token) {
            return false;
        }

        const allowedTypes = this.expectedTypeId === null ? null : [].concat(this.expectedTypeId);
        if (allowedTypes !== null && !allowedTypes.includes(Number(user?.type_id ?? user?.typeId))) {
            this.storage.clearSession();
            return false;
        }

        return true;
    }

    protect() {
        if (!this.isAuthenticated()) {
            window.location.replace(this.redirectUrl);
            return false;
        }

        return true;
    }
}
