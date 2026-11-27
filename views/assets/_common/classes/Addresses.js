import HttpClientBase from "./HttpClientBase.js";
import Address from "./Address.js";

export default class Addresses extends HttpClientBase {
    async listAll() {
        const response = await this.get("/addresses/list");
        if (!Array.isArray(response?.data)) throw new Error("A API não retornou os endereços.");
        response.data = response.data.map((address) => Address.fromAPI(address));
        return response;
    }

    async listById(id) {
        return this.#withAddress(await this.get("/addresses/list/:address_id", { address_id: id }));
    }

    async insert(data) {
        const address = data instanceof Address ? data : new Address(data);
        return this.#withAddress(await this.postForm("/addresses/", address.toPayload()));
    }

    async update(id, data) {
        const address = data instanceof Address ? data : new Address(data);
        return this.#withAddress(await this.putForm("/addresses/:address_id", address.toPayload(), { address_id: id }));
    }

    async remove(id) {
        return this.delete("/addresses/:address_id", { address_id: id });
    }

    #withAddress(response) {
        if (!response?.data || Array.isArray(response.data) || typeof response.data !== "object") {
            throw new Error("A API retornou uma resposta inesperada para o endereço.");
        }
        response.data = Address.fromAPI(response.data);
        return response;
    }
}
