export default class HttpClientBase {
    #baseUrl;
    #defaultHeaders;

    constructor(baseUrl = new URL("../../../../api", import.meta.url).href.replace(/\/$/, "")) {
        this.#baseUrl = baseUrl;
        this.#defaultHeaders = {
            "Content-Type": "application/json"
        };
    }

    setAuthToken(token) {
        this.#defaultHeaders["Authorization"] = `Bearer ${token}`;
    }

    clearAuthToken() {
        delete this.#defaultHeaders["Authorization"];
    }

    toFormData(data = {}) {
        if (data instanceof FormData) {
            return data;
        }

        if (typeof HTMLFormElement !== "undefined" && data instanceof HTMLFormElement) {
            return new FormData(data);
        }

        const formData = new FormData();
        Object.entries(data).forEach(([key, value]) => {
            // null/undefined não são enviados (evita o texto "null" no backend)
            if (value !== null && value !== undefined) {
                formData.append(key, value);
            }
        });
        return formData;
    }

    toUrlSearchParams(data = {}) {
        if (data instanceof URLSearchParams) {
            return data;
        }

        if (typeof HTMLFormElement !== "undefined" && data instanceof HTMLFormElement) {
            return new URLSearchParams(new FormData(data));
        }

        if (data instanceof FormData) {
            return new URLSearchParams(data);
        }

        // null/undefined não são enviados (evita o texto "null" no backend)
        return new URLSearchParams(Object.entries(data).filter(([, value]) => value !== null && value !== undefined));
    }

    #buildUrl(endpoint, params = {}) {
        let url = `${this.#baseUrl}${endpoint}`;

        const queryParams = new URLSearchParams();
        for (const [key, value] of Object.entries(params)) {
            if (url.includes(`/:${key}`)) {
                url = url.replace(`:${key}`, value);
            } else {
                queryParams.append(key, value);
            }
        }

        const queryString = queryParams.toString();
        if (queryString) {
            url += `?${queryString}`;
        }

        return url;
    }

    async #fetchWithConfig(endpoint, config, params = {}) {
        const { _isFormData, ...fetchConfig } = config;
        let response;

        try {
            const url = this.#buildUrl(endpoint, params);

            const headers = { ...this.#defaultHeaders, ...fetchConfig.headers };
            if (_isFormData) {
                delete headers["Content-Type"];
            }

            response = await fetch(url, {
                ...fetchConfig,
                headers
            });
        } catch (error) {
            throw new Error("Não foi possível conectar ao servidor. Tente novamente.");
        }

        const contentType = response.headers.get("content-type");
        if (!contentType || !contentType.includes("application/json")) {
            throw new Error(`A API retornou uma resposta inesperada (HTTP ${response.status}).`);
        }

        const result = await response.json();

        // Respostas 4xx/5xx ou com type "error" viram exceção com a mensagem da API.
        if (!response.ok || result?.type === "error") {
            const error = new Error(result?.message || `Erro HTTP ${response.status}`);
            error.status = response.status;
            throw error;
        }

        return result;
    }

    async get(endpoint, params = {}, headers = {}) {
        return this.#fetchWithConfig(endpoint, {
            method: "GET",
            headers
        }, params);
    }

    async post(endpoint, data = {}, params = {}) {
        const isFormData = data instanceof FormData;

        return this.#fetchWithConfig(endpoint, {
            method: "POST",
            body: isFormData ? data : JSON.stringify(data),
            _isFormData: isFormData
        }, params);
    }

    async postForm(endpoint, data = {}, params = {}) {
        return this.post(endpoint, this.toFormData(data), params);
    }

    async put(endpoint, data = {}, params = {}) {
        const isFormData = data instanceof FormData;

        return this.#fetchWithConfig(endpoint, {
            method: "PUT",
            body: isFormData ? data : JSON.stringify(data),
            _isFormData: isFormData
        }, params);
    }

    async putForm(endpoint, data = {}, params = {}) {
        const body = this.toUrlSearchParams(data);
        return this.#fetchWithConfig(endpoint, {
            method: "PUT",
            body: body.toString(),
            headers: {
                "Content-Type": "application/x-www-form-urlencoded"
            }
        }, params);
    }

    async delete(endpoint, params = {}) {
        return this.#fetchWithConfig(endpoint, {
            method: "DELETE"
        }, params);
    }
}
