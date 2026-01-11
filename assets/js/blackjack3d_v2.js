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

        // Extended chip values for larger bets
        const chipValues = [1000, 500, 100, 50, 25, 10];
        let remaining = amount;
        let yOffset = 0;
        let chipCount = 0;
        const maxChips = 15; // Allow more chips for larger bets

        for (const val of chipValues) {
            while (remaining >= val && chipCount < maxChips) {
                const chip = document.createElement('div');
                chip.className = `chip chip-${val}`;
                chip.textContent = val >= 1000 ? (val / 1000) + 'K' : val;
                chip.style.bottom = `${yOffset}px`;
                chip.style.zIndex = chipCount;
                stack.appendChild(chip);
                yOffset += 3; // Slightly tighter stacking
                remaining -= val;
                chipCount++;
            }
        }

        // Show remaining amount if chips couldn't represent it all
        if (remaining > 0 && chipCount > 0) {
            const lastChip = stack.lastChild;
            if (lastChip) {
                lastChip.textContent = '+';
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

        // Check if current_hand changed for any player (split hand switch) - reload to sync PHP UI
        if (this.gameState?.players && newState?.players) {
            for (let i = 0; i < newState.players.length; i++) {
                const oldPlayer = this.gameState.players[i];
                const newPlayer = newState.players[i];
                if (oldPlayer && newPlayer && oldPlayer.id === newPlayer.id) {
                    const oldHand = oldPlayer.current_hand || 'main';
                    const newHand = newPlayer.current_hand || 'main';
                    if (oldHand !== newHand) {
                        console.log('🔄 Player hand switched from', oldHand, 'to', newHand, '- reloading page');
                        location.reload();
                        return;
                    }
                }
            }
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
        // The server already handles card visibility via dealer_cards_visible
        const dealerCardData = this.gameState.dealer_cards_visible || [];

        dealerCardData.forEach((c, idx) => {
            // Server sends 'hidden' suit for face-down cards
            const faceUp = c.suit !== 'hidden';

            const card = this.createCard(c.suit, c.value, faceUp);
            card.classList.add('dealing');
            card.style.animationDelay = `${idx * 0.15}s`;
            dealerCards.appendChild(card);
        });

        // Update dealer value (show "?" if any card is hidden)
        const dealerValue = document.getElementById('dealer-value');
        if (dealerValue) {
            const hasHiddenCard = dealerCardData.some(c => c.suit === 'hidden');
            if (hasHiddenCard) {
                dealerValue.textContent = '?';
            } else {
                dealerValue.textContent = this.gameState.dealer_value || '-';
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

            // Check if player has split
            const hasSplit = player.has_split || false;
            const currentHand = player.current_hand || 'main';
            const splitCards = player.split_cards || [];

            if (hasSplit && splitCards.length > 0) {
                // Split display: stack both hands with clear separation
                const cards = player.cards || [];

                // Main hand cards (normal position)
                cards.forEach((c, idx) => {
                    const card = this.createCard(c.suit, c.value, true);
                    card.classList.add('dealing');
                    card.style.animationDelay = `${idx * 0.15}s`;
                    if (currentHand !== 'main') card.style.opacity = '0.6';
                    playerCardsEl.appendChild(card);
                });

                // Divider between hands
                const divider = document.createElement('div');
                divider.style.cssText = 'width: 2px; height: 40px; background: gold; margin: 0 5px; border-radius: 2px;';
                playerCardsEl.appendChild(divider);

                // Split hand cards
                splitCards.forEach((c, idx) => {
                    const card = this.createCard(c.suit, c.value, true);
                    card.classList.add('dealing');
                    card.style.animationDelay = `${(cards.length + idx) * 0.15}s`;
                    if (currentHand === 'main') card.style.opacity = '0.6';
                    if (currentHand === 'split') card.style.boxShadow = '0 0 10px gold';
                    playerCardsEl.appendChild(card);
                });
            } else {
                // Normal display - no split
                const cards = player.cards || [];
                cards.forEach((c, idx) => {
                    const card = this.createCard(c.suit, c.value, true);
                    card.classList.add('dealing');
                    card.style.animationDelay = `${idx * 0.15}s`;
                    playerCardsEl.appendChild(card);
                });
            }

            // Chips - show more for split (double bet)
            if (player.bet_amount > 0 && playerChipsEl) {
                const chipStack = this.createChipStack(player.bet_amount);
                playerChipsEl.appendChild(chipStack);

                // If split, add second chip stack for the split bet
                if (hasSplit) {
                    const splitChipStack = this.createChipStack(player.bet_amount);
                    splitChipStack.style.marginLeft = '15px';
                    playerChipsEl.appendChild(splitChipStack);
                }
            }

            // Info - show both hand values if split
            if (playerInfo) {
                if (hasSplit) {
                    const mainValue = this.calculateHandValue(player.cards || []);
                    const splitValue = this.calculateHandValue(splitCards);
                    playerInfo.innerHTML = `
                        <div class="username">${player.twitch_username || 'Joueur'}</div>
                        <div class="hand-value">Main: ${mainValue} | Split: ${splitValue}</div>
                    `;
                } else {
                    playerInfo.innerHTML = `
                        <div class="username">${player.twitch_username || 'Joueur'}</div>
                        <div class="hand-value">Main: ${player.hand_value || 0}</div>
                    `;
                }
                playerInfo.style.display = 'block';
            }
        });
    }

    // Calculate hand value (same logic as PHP)
    calculateHandValue(cards) {
        if (!cards || !Array.isArray(cards)) return 0;

        let value = 0;
        let aces = 0;

        cards.forEach(card => {
            const cardValue = card.value;
            if (['J', 'Q', 'K'].includes(cardValue)) {
                value += 10;
            } else if (cardValue === 'A') {
                value += 11;
                aces++;
            } else {
                value += parseInt(cardValue);
            }
        });

        // Adjust aces if over 21
        while (value > 21 && aces > 0) {
            value -= 10;
            aces--;
        }

        return value;
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
