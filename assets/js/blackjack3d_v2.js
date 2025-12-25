/**
 * N0THY CASINO - Blackjack CSS 3D Engine
 * Uses CSS transforms for 3D effect instead of Three.js
 * Inspired by Blackjack-master example
 */

class Blackjack3D {
    constructor(containerId, gameState) {
        this.container = document.getElementById(containerId);
        this.gameState = gameState;
        this.init();
    }

    init() {
        // Clear container
        this.container.innerHTML = '';
        this.container.className = 'blackjack-3d-container';

        // Create table structure
        this.createTable();

        // Render current game state
        this.renderGameState();
    }

    createTable() {
        // Main table felt
        const table = document.createElement('div');
        table.className = 'blackjack-table';
        table.id = 'blackjack-felt';

        // Dealer area
        const dealerArea = document.createElement('div');
        dealerArea.className = 'dealer-area';
        dealerArea.id = 'dealer-cards';
        table.appendChild(dealerArea);

        // Player areas
        const playerAreas = document.createElement('div');
        playerAreas.className = 'player-areas';
        playerAreas.id = 'player-areas';

        // Create 5 player spots
        for (let i = 1; i <= 5; i++) {
            const playerArea = document.createElement('div');
            playerArea.className = 'player-area';
            playerArea.id = `player-spot-${i}`;

            const cards = document.createElement('div');
            cards.className = 'player-cards';
            cards.id = `player-${i}-cards`;
            playerArea.appendChild(cards);

            const chips = document.createElement('div');
            chips.className = 'chip-stack';
            chips.id = `player-${i}-chips`;
            playerArea.appendChild(chips);

            const info = document.createElement('div');
            info.className = 'player-info';
            info.id = `player-${i}-info`;
            info.style.display = 'none';
            playerArea.appendChild(info);

            playerAreas.appendChild(playerArea);
        }

        table.appendChild(playerAreas);

        // Accessories
        const shoe = document.createElement('div');
        shoe.className = 'card-shoe';
        table.appendChild(shoe);

        const tray = document.createElement('div');
        tray.className = 'discard-tray';
        table.appendChild(tray);

        this.container.appendChild(table);

        // Dealer info display
        const dealerInfo = document.createElement('div');
        dealerInfo.className = 'dealer-info';
        dealerInfo.id = 'dealer-info';
        dealerInfo.innerHTML = 'Dealer: <span id="dealer-value">-</span>';
        this.container.appendChild(dealerInfo);
    }

    createCard(suit, value, faceUp = true) {
        const card = document.createElement('div');

        if (!faceUp || suit === 'hidden') {
            card.className = 'card face-down';
        } else {
            const isRed = (suit === 'hearts' || suit === 'diamonds');
            card.className = `card ${isRed ? 'red' : 'black'}`;

            const symbols = {
                'hearts': '♥',
                'diamonds': '♦',
                'clubs': '♣',
                'spades': '♠'
            };
            const symbol = symbols[suit] || '?';

            card.innerHTML = `
                <span class="card-value">${value}</span>
                <span class="card-suit-small">${symbol}</span>
                <span class="card-suit-large">${symbol}</span>
                <span class="card-value-bottom">${value}</span>
                <span class="card-suit-bottom">${symbol}</span>
            `;
        }

        return card;
    }

    createChipStack(amount) {
        const stack = document.createElement('div');
        stack.className = 'chip-stack';

        const chipValues = [100, 50, 25, 10, 5];
        let remaining = amount;
        let yOffset = 0;
        let chipCount = 0;

        for (const val of chipValues) {
            while (remaining >= val && chipCount < 8) {
                const chip = document.createElement('div');
                chip.className = `chip chip-${val}`;
                chip.textContent = val;
                chip.style.bottom = `${yOffset}px`;
                stack.appendChild(chip);
                yOffset += 4;
                remaining -= val;
                chipCount++;
            }
        }

        return stack;
    }

    updateGameState(newState) {
        // Check if state actually changed to prevent flickering
        const newHash = JSON.stringify(newState);
        if (this.lastStateHash === newHash) {
            return; // No change, skip re-render
        }

        // Check if status changed - reload page to sync sidebar
        const oldStatus = this.gameState?.status;
        const newStatus = newState?.status;
        if (oldStatus && newStatus && oldStatus !== newStatus) {
            console.log('🔄 Status changed from', oldStatus, 'to', newStatus, '- reloading page');
            location.reload();
            return;
        }

        // Check if card count changed significantly (cards were dealt) - reload to sync PHP UI
        const oldCardCount = this.getTotalCardCount(this.gameState);
        const newCardCount = this.getTotalCardCount(newState);
        if (oldCardCount !== undefined && newCardCount > oldCardCount) {
            console.log('🃏 Cards dealt:', oldCardCount, '→', newCardCount, '- reloading page');
            location.reload();
            return;
        }

        this.lastStateHash = newHash;
        this.gameState = newState;
        this.renderGameState();
    }

    // Helper to count total cards in game
    getTotalCardCount(state) {
        if (!state) return undefined;
        let count = (state.dealer_cards_visible || []).length;
        (state.players || []).forEach(p => {
            count += (p.cards || []).length;
        });
        return count;
    }

    renderGameState() {
        if (!this.gameState) return;

        // Only render if we haven't rendered this exact state yet (prevents flickering)
        const stateHash = JSON.stringify({
            dealer: this.gameState.dealer_cards_visible,
            players: this.gameState.players?.map(p => ({
                id: p.id,
                cards: p.cards,
                bet: p.bet_amount,
                status: p.status
            })),
            status: this.gameState.status
        });

        if (this.lastRenderedHash === stateHash) {
            return; // Already rendered this state, skip
        }
        this.lastRenderedHash = stateHash;

        // Clear previous cards
        const dealerCards = document.getElementById('dealer-cards');
        if (dealerCards) dealerCards.innerHTML = '';

        for (let i = 1; i <= 5; i++) {
            const playerCards = document.getElementById(`player-${i}-cards`);
            const playerChips = document.getElementById(`player-${i}-chips`);
            const playerInfo = document.getElementById(`player-${i}-info`);
            if (playerCards) playerCards.innerHTML = '';
            if (playerChips) playerChips.innerHTML = '';
            if (playerInfo) playerInfo.style.display = 'none';
        }

        // Render dealer cards
        // Second card should be face-down during player turns only
        const dealerCardData = this.gameState.dealer_cards_visible || [];
        const gameStatus = this.gameState.status || '';
        const showSecondCard = (gameStatus === 'dealer_turn' || gameStatus === 'completed' || gameStatus === 'paying' || gameStatus === 'finished');

        dealerCardData.forEach((c, idx) => {
            // Second card (index 1) is hidden unless it's dealer's turn or game completed
            const shouldShowCard = (idx === 0) || showSecondCard || c.suit === 'hidden';
            const faceUp = shouldShowCard && c.suit !== 'hidden';

            const card = this.createCard(c.suit, c.value, faceUp);
            card.classList.add('dealing');
            card.style.animationDelay = `${idx * 0.15}s`;
            dealerCards.appendChild(card);
        });

        // Update dealer value (show "?" if second card hidden)
        const dealerValue = document.getElementById('dealer-value');
        if (dealerValue) {
            if (showSecondCard || dealerCardData.length <= 1) {
                dealerValue.textContent = this.gameState.dealer_value || '-';
            } else {
                // Only show first card value during player turns
                dealerValue.textContent = '?';
            }
        }

        // Render player cards and chips
        const players = this.gameState.players || [];
        players.forEach((player) => {
            const seatNum = player.seat_number || 1;
            const playerCardsEl = document.getElementById(`player-${seatNum}-cards`);
            const playerChipsEl = document.getElementById(`player-${seatNum}-chips`);
            const playerInfo = document.getElementById(`player-${seatNum}-info`);

            if (!playerCardsEl) return;

            // Cards
            const cards = player.cards || [];
            cards.forEach((c, idx) => {
                const card = this.createCard(c.suit, c.value, true);
                card.classList.add('dealing');
                card.style.animationDelay = `${idx * 0.15}s`;
                playerCardsEl.appendChild(card);
            });

            // Chips
            if (player.bet_amount > 0 && playerChipsEl) {
                const chipStack = this.createChipStack(player.bet_amount);
                playerChipsEl.appendChild(chipStack);
            }

            // Info
            if (playerInfo) {
                playerInfo.innerHTML = `
                    <div class="username">${player.twitch_username || 'Joueur'}</div>
                    <div class="hand-value">Main: ${player.hand_value || 0}</div>
                `;
                playerInfo.style.display = 'block';
            }
        });
    }

    // Animation helper for dealing cards
    animateDealCard(targetElement) {
        if (targetElement) {
            targetElement.classList.add('dealing');
        }
    }

    // Animation for winner
    animateWinner(seatNumber) {
        const playerArea = document.getElementById(`player-spot-${seatNumber}`);
        if (playerArea) {
            playerArea.style.animation = 'pulse 0.5s ease-in-out 3';
        }
    }

    // Connect to SSE for real-time updates
    connectSSE(tableId) {
        if (this.eventSource) {
            this.eventSource.close();
        }

        const url = `api/sse_stream.php?table_id=${tableId}`;
        this.eventSource = new EventSource(url);

        this.eventSource.addEventListener('gamestate', (event) => {
            try {
                const newState = JSON.parse(event.data);
                this.updateGameState(newState);
                console.log('🎰 Game state updated via SSE');
            } catch (e) {
                console.error('SSE parse error:', e);
            }
        });

        this.eventSource.addEventListener('heartbeat', (event) => {
            // Keep-alive, no action needed
        });

        this.eventSource.addEventListener('error', (event) => {
            console.error('SSE error:', event);
            // Attempt reconnect after 5 seconds
            setTimeout(() => this.connectSSE(tableId), 5000);
        });

        this.eventSource.onerror = () => {
            console.log('SSE connection lost, reconnecting...');
            setTimeout(() => this.connectSSE(tableId), 3000);
        };

        console.log('🔌 SSE connected for table', tableId);
    }

    // Disconnect SSE
    disconnectSSE() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
            console.log('🔌 SSE disconnected');
        }
    }
}

window.Blackjack3D = Blackjack3D;
