import HttpClientBase from "./HttpClientBase.js";
import FaqCategory from "./FaqCategory.js";

export default class FaqCategories extends HttpClientBase {
    async listAll() {
        const response = await this.get("/faqs-categories");
        if (!Array.isArray(response?.data)) {
            throw new Error("A API não retornou uma lista de categorias.");
        }
        response.data = response.data.map((category) => FaqCategory.fromAPI(category));
        return response;
    }

    async listById(id) {
        return this.get("/faqs-categories/:id", { id });
    }

    async insert(data) {
        const category = data instanceof FaqCategory ? data : new FaqCategory(data);
        return this.postForm("/faqs-categories", category.toPayload());
    }

    async update(id, data) {
        const category = data instanceof FaqCategory ? data : new FaqCategory(data);
        return this.putForm("/faqs-categories/:id", category.toPayload(), { id });
    }

    async remove(id) {
        return this.delete("/faqs-categories/:id", { id });
    }
}
