import HttpClientBase from "./HttpClientBase.js";
import Contact from "./Contact.js";

export default class Contacts extends HttpClientBase {
    async listAll() {
        const response = await this.get("/contacts/list");
        if (!Array.isArray(response?.data)) throw new Error("A API não retornou as mensagens de contato.");
        response.data = response.data.map((contact) => Contact.fromAPI(contact));
        return response;
    }

    async insert(data) {
        const contact = data instanceof Contact ? data : new Contact(data);
        return this.postForm("/contacts/", contact.toPayload());
    }

    async remove(id) {
        return this.delete("/contacts/:contact_id", { contact_id: id });
    }
}
