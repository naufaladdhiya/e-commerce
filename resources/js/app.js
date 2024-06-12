import "./bootstrap";
import "preline";

document.addEventListener("livewire:navigated", () => {
    window.HSSStaticMethods.autoInit();
});
