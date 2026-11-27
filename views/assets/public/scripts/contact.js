import Contacts from "../../_common/classes/Contacts.js";
import Contact from "../../_common/classes/Contact.js";
import { createFormController } from "../../_common/classes/FormController.js";

const form = document.querySelector("[data-contact-form]");
const contacts = new Contacts();

if (form) {
    const controller = createFormController(form);

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        let contact;
        try {
            contact = new Contact(controller.read());
            contact.validate();
        } catch (error) {
            window.EduUI?.showToast(error.message, "warning");
            return;
        }

        controller.setLoading(true, "Enviando...");
        try {
            const response = await contacts.insert(contact);
            window.EduUI?.showToast(response, "success");
            controller.clear();
        } catch (error) {
            window.EduUI?.showToast(error, "error");
        } finally {
            controller.setLoading(false);
        }
    });
}
