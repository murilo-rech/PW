import HttpClientBase from "./HttpClientBase.js";
import Faq from "./Faq.js";

export default class Faqs extends HttpClientBase {
    async listAll() {
        return this.#mapFaqs(await this.get("/faqs"));
    }

    async listAllAdmin() {
        return this.#mapFaqs(await this.get("/faqs/admin/list"));
    }

    async listById(id) {
        return this.#mapOne(await this.get("/faqs/:id", { id }));
    }

    async listByCategory(categoryId) {
        return this.#mapFaqs(await this.get("/faqs/category/:categoryId", { categoryId }));
    }

    async insert(data) {
        return this.#mapOne(await this.postForm("/faqs", this.#payload(data)));
    }

    async update(id, data) {
        const payload = data instanceof Faq ? data.toPayload() : data;
        return this.#mapOne(await this.putForm("/faqs/:id", payload, { id }));
    }

    async remove(id) {
        return this.delete("/faqs/:id", { id });
    }

    #payload(data) {
        const faq = data instanceof Faq ? data : new Faq(data);
        return faq.toPayload();
    }

    #mapOne(response) {
        if (!response?.data || Array.isArray(response.data) || typeof response.data !== "object") {
            throw new Error("A API retornou uma resposta inesperada para a FAQ.");
        }
        response.data = Faq.fromAPI(response.data);
        return response;
    }

    #mapFaqs(response) {
        if (!Array.isArray(response?.data)) {
            throw new Error("A API não retornou uma lista de FAQs.");
        }
        response.data = response.data.map((faq) => Faq.fromAPI(faq));
        return response;
    }
}
