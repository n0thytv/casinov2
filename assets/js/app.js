// N0thy Casino - JavaScript
document.addEventListener('DOMContentLoaded', function () {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.animation = 'fadeIn 0.3s ease-out reverse';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

// Fonction pour rafraîchir le solde
async function refreshBalance() {
    const balanceElement = document.getElementById('balance-amount');
    const refreshBtn = document.getElementById('refresh-btn');

    if (!balanceElement) return;

    // Désactiver le bouton et afficher le loader
    if (refreshBtn) {
        refreshBtn.disabled = true;
        refreshBtn.innerHTML = '<span class="loading-spinner-small"></span> Chargement...';
    }

    try {
        const response = await fetch('api/get_balance.php');
        const data = await response.json();

        if (data.success) {
            // Animation de compteur
            animateCounter(balanceElement, data.balance);
        } else {
            console.error('Erreur:', data.error);
        }
    } catch (error) {
        console.error('Erreur réseau:', error);
    } finally {
        if (refreshBtn) {
            refreshBtn.disabled = false;
            refreshBtn.innerHTML = '🔄 Rafraîchir';
        }
    }
}

// Animation de compteur
function animateCounter(element, targetValue) {
    const startValue = parseInt(element.textContent.replace(/\D/g, '')) || 0;
    const duration = 1000;
    const startTime = performance.now();

    function formatToSpans(val) {
        // Formatage avec séparateur milliers (espace)
        const formatted = val.toLocaleString('fr-FR');
        let html = '';
        for (let char of formatted) {
            // Détection espace classique ou insécable
            if (/\s/.test(char) || char === '\u00A0' || char === '\u202F') {
                html += '<span class="balance-sep">&nbsp;</span>';
            } else {
                html += '<span class="balance-digit">' + char + '</span>';
            }
        }
        return html;
    }

    function update(currentTime) {
        const elapsed = currentTime - startTime;
        const progress = Math.min(elapsed / duration, 1);

        // Easing function
        const easeOutQuart = 1 - Math.pow(1 - progress, 4);
        const currentValue = Math.floor(startValue + (targetValue - startValue) * easeOutQuart);

        element.innerHTML = formatToSpans(currentValue);

        if (progress < 1) {
            requestAnimationFrame(update);
        }
    }

    requestAnimationFrame(update);
}

// Confirmation pour les actions admin
function confirmAction(action, username) {
    const messages = {
        approve: `Voulez-vous vraiment approuver l'inscription de "${username}" ?`,
        reject: `Voulez-vous vraiment refuser l'inscription de "${username}" ?`
    };

    return confirm(messages[action] || 'Confirmer cette action ?');
}
