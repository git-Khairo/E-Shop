import './bootstrap';
import 'preline';
import 'flowbite';


const modalElement = document.getElementById("default-modal");

if (modalElement) {
    // Create a new Flowbite Modal instance
    const modal = new Modal(modalElement);
    
    // Handle the close button with data-modal-hide
    modalElement.querySelectorAll("[data-modal-hide]").forEach((button) => {
        button.addEventListener("click", () => {
            modal.hide();
        });
    });

    // Close the modal when clicking outside (on the backdrop)
    modalElement.addEventListener("click", (event) => {
        if (event.target === modalElement) {
            modal.hide();
        }
    });
}

