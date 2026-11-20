import HttpClientBase from "./HttpClientBase.js";
import User from "./User.js";

export default class Users extends HttpClientBase {
    async login(email, password) {
        return this.#withUser(await this.postForm("/users/login", { email, password }));
    }

    async loginFromForm(form) {
        const data = new FormData(form);
        return this.login(data.get("email"), data.get("password"));
    }

    async loginAdmin(email, password) {
        return this.#withUser(await this.postForm("/users/login-admin", { email, password }));
    }

    async loginAdminFromForm(form) {
        const data = new FormData(form);
        return this.loginAdmin(data.get("email"), data.get("password"));
    }

    async register(data) {
        return this.#withUser(await this.postForm("/users/register", data));
    }

    async registerFromForm(form) {
        const data = new FormData(form);
        const user = new User({
            name: data.get("name"),
            email: data.get("email"),
            password: data.get("password"),
            type_id: data.get("profile") === "professor" ? 3 : 4,
            specialization: data.get("department") || null
        });

        return this.register({
            name: user.name,
            email: user.email,
            password: user.password,
            type_id: user.typeId,
            specialization: user.specialization,
            subjects: data.get("subjects") || null,
            school_code: data.get("school-code") || null
        });
    }

    async update(data) {
        return this.#withUser(await this.putForm("/users/update", data));
    }

    async updateAdmin(data) {
        return this.#withUser(await this.putForm("/users/update-admin", data));
    }

    async getMe() {
        return this.#withUser(await this.get("/users/me"));
    }

    async uploadPhoto(data) {
        return this.post("/users/photo", data);
    }

    async list() {
        const response = await this.get("/users/list");
        if (!Array.isArray(response?.data)) {
            throw new Error("A API retornou uma lista inesperada de usuários.");
        }
        response.data = response.data.map((user) => User.fromAPI(user));
        return response;
    }

    async find(id) {
        return this.#withUser(await this.get("/users/list/:user_id", { user_id: id }));
    }

    async createAdmin(data) {
        return this.#withUser(await this.postForm("/users/register-admin", data));
    }

    async createInSchool(data) {
        return this.#withUser(await this.postForm("/users/register-school", {
            type_id: Number(data.type_id),
            name: data.name,
            email: data.email,
            password: data.password,
            phone: data.phone || null,
            registration_number: data.registration_number || null,
            specialization: data.specialization || null,
            office_room: data.office_room || null
        }));
    }

    async updateById(id, data) {
        const values = data instanceof User ? data.toPayload() : data;
        const allowedFields = [
            "school_id", "type_id", "name", "email", "password", "phone",
            "registration_number", "specialization", "office_room", "active"
        ];
        const payload = Object.fromEntries(
            Object.entries(values)
                .filter(([field, value]) => allowedFields.includes(field)
                    && (field === "school_id" || (value !== null && value !== "")))
                // school_id vazio desvincula o administrador da escola
                .map(([field, value]) => [field, field === "school_id" && value === null ? "" : value])
        );
        return this.#withUser(await this.putForm("/users/admin/:user_id", payload, { user_id: id }));
    }

    async remove(id) {
        return this.delete("/users/admin/:user_id", { user_id: id });
    }

    #withUser(response) {
        if (!response?.data || Array.isArray(response.data) || typeof response.data !== "object") {
            throw new Error("A API retornou uma resposta inesperada para o usuário.");
        }
        response.data = User.fromAPI(response.data);
        return response;
    }
}
