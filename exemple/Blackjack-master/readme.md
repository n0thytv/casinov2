# Blackjack
### A simple front-end casino game app. 

***

[Overview](https://github.com/AndyTuttle12/Blackjack#overview)   |   [Technologies Used](https://github.com/AndyTuttle12/Blackjack#technologies)   |   [Dependencies](https://github.com/AndyTuttle12/Blackjack#dependencies)   |   [MVP](https://github.com/AndyTuttle12/Blackjack#mvp)   |  [Challenges and Solutions](https://github.com/AndyTuttle12/Blackjack#challenges-and-solutions)   |   [Code Snippets](https://github.com/AndyTuttle12/Blackjack#code-snippets)   |   [Screen Shots](https://github.com/AndyTuttle12/Blackjack#screen-shots)   |   [Live Demo](https://github.com/AndyTuttle12/Blackjack#demo)

---

## Overview

This project started out as a simple game to demonstrate jQuery DOM manipulation and add some CSS classes dynamically. That quickly became a reality, and after making the basic app work with the rules of a simple blackjack game, I added more and more features! The first goal was to make sure all the logic for the gameplay worked for every edge case, and then to style the game to look like a real blackjack table with dealing animations, shuffling, and 3D perspective. 

For each win or loss, I created different messages and outcomes based on the standard rules of casino blackjack, and incorporated a drag and drop system with jQuery UI to create a fully interactive betting area. Then, after many tweaks to the visual aspect of the betting area, I added fully interactive chips, bet resets, and custom betting options. 

The final update added new features to the game adding quite a bit of complexity, and real Vegas inspired gameplay:

- I added logic to double down after the initial deal,
- a fully integrated bank system where every bet action affects the player's total, 
- the ability to purchase insurance on the deal if the dealer has an Ace showing,
- and finally, a very complex system of second side pots and a whole new had specifically for the option to split a deal of two cards of the same value.

---

## Technologies

Made with: 
- Javascript
- jQuery
- jQuery UI
- Bootstrap
- HTML/CSS

---

## Dependencies

None, just simple front-end logic and css trickery!

---

## MVP

Starting this project, I built a simple game that had rules and a win condition. Going from there, my next set of goals included:
- a visual representation of the table
- a simple animation for dealing cards
- an interactive betting area

Recently, I completed more of the features that were reserved as stretch goals:
- a full betting system with minimums, splits, insurance, double downs and a bank
- updated visual and interactive chips

---

## Challenges and Solutions


**Challenge 1:**
For each card added to a hand, and for each additional interaction, there needed to be a simple animation to show the player and dealer cards. This was expecially a challenge since there was a CSS perspective property on the whole table and every object on the table. 

**Solution 1:**
To add this animation, I set custom positions for each card in each hand (player, dealer, and split hand) and for each, added dynamic classes upon the card's creation to trigger keyframes, animations, and specific display settings. This was much more involved than I thought, but in the end, it provides a nice effect for each part of a regular game.

**Challenge 2:**
The other challenging positioning and dynamic styling component to the blackjack game was the inclusion of draggable chips and a droppable betting area. With the current positioning of each chip and the jQuery UI draggable/droppable events, it proved difficult to get the chips to interact in a way that would update bets and calculate the totals.

**Solution 2:**
I added more dynamic selectors to each chip and on reset re-rendered all chips to original amounts. Each selector was targeted and chips were updated based on the clone dropping on the bet area. I updated a new chip every time there was a drop and based the calculations on that chip. Then, dynamically, added the dialog for the bet amount and included an interface to reset bets before the deal.

**Challenge 3:**
Probably the most complicated logic in the enire game was the inclusion of the function to check the win condition of a hand. This involved all sorts of bank adjustments, a massive tree of conditionals, and many custom modals based on the output of the final hand as the dealer drew cards.

**Solution 3:**
The final product of the function became very convoluted and specific to the logic of a blackjack table in a Vegas casino. I had to check if the dealer got a blackjack, if the player busted, dealer busted, or a combination of the two, and almost half of the function was dedicated to the custom split feature where the player is playing two hands, and the dealer has on as well. That split function added a ton of additional logic and tests to every part of the game.

---

## Code Snippets

Here are some examples from the challenges above:

This function is the entire event for the initial deal from the new deck.
It shuffles the deck, adds cards to the player's and dealer's hands and deals them with a custom class that triggers the animation in css.
Additionally, this checks for insurance, splits, blackjack (dealer and player) and updates the totals.

```javascript
$('.deal-button').click(function(){
		bankTotal -= currentBet;
		$('#bankAmount').html(bankTotal);
		shuffleDeck(); // now shuffled!
		playersHand.push(theDeck.shift());
		dealersHand.push(theDeck.shift());
		playersHand.push(theDeck.shift());
		dealersHand.push(theDeck.shift());
		if(dealersHand[0].length === 2 && dealersHand[0][0] === '1'){
			$('.insurance-button').show();
		}
		$('.player-cards .card-1').addClass('dealt1');
		placeCard('player',1,playersHand[0]);
		setTimeout(function(){
			$('.player-cards .card-2').addClass('dealt2');
			placeCard('player',2,playersHand[1]);
		}, 800);
		setTimeout(function(){
		$('.dealer-cards .card-1').addClass('dealerDealt1');
		placeCard('dealer',1,dealersHand[0]);
		}, 400);
		setTimeout(function(){
			$('.dealer-cards .card-2').addClass('dealerDealt2');
			placeCard('dealer',2,dealersHand[1]);
		}, 1200);
		if(playersHand[0].length === 3 && playersHand[1].length === 3){
			if(playersHand[0].slice(0,2) === playersHand[1].slice(0,2)){
				$('.split-button').show();
			}
		}
		else if(playersHand[0].length === 2 && playersHand[1].length === 2){
			if(playersHand[0][0] === playersHand[1][0]){
				$('.split-button').show();
			}
		}
		hiddenDealerCard = dealersHand[1];
		calculateTotal(playersHand, 'player');
		calculateTotal(dealersHand, 'dealer');
		checkBlackJack();
		$('.deal-button').attr('disabled', 'disabled');
		$('.hit-button').removeAttr('disabled', 'disabled');
		$('.stand-button').removeAttr('disabled', 'disabled');
		$('.double-button').removeAttr('disabled', 'disabled');
		$('#bet-reset').hide();
	});
```
---

Here is the function that fires whenever a chip is dropped on the bet area.
Then, the bet is calculated and based on the bet, the bank is updated.

```javascript
function droppedChip(event, ui){
	var currentChip = ui.draggable;	
	ui.draggable.draggable({revert:false,opacity:1,helper:'original',margin:0});
	calculateBet();
	$(this).append($(ui.draggable).draggable({revert:true,containment:'#the-table',cursor:'pointer',cursorAt:{ top: 18, left: 29 },position:{ top:50, left:112 }}));
	$('.deal-button').removeAttr('disabled', 'disabled');
	$('.bet-amount').show();
	$('#bet-reset').show();
	ui.helper.draggable({revert:false,opacity:1});
}
function calculateBet(){
	betChips = betChips;
	var selectedChip = $('.activeChip');
	for(let i = 0; i < selectedChip.length; i++){
		if(selectedChip[i].id === ''){
			if(selectedChip[i].className=='activeChip fiveChip ui-draggable ui-draggable-handle ui-draggable-dragging'){
				betChips.push(5);
				break;
			}
			else if(selectedChip[i].className=='activeChip tenChip ui-draggable ui-draggable-handle ui-draggable-dragging'){
				betChips.push(10);
				break;
			}
			else if(selectedChip[i].className=='activeChip twentyFiveChip ui-draggable ui-draggable-handle ui-draggable-dragging'){
				betChips.push(25);
				break;
			}
			else if(selectedChip[i].className=='activeChip hundredChip ui-draggable ui-draggable-handle ui-draggable-dragging'){
				betChips.push(100);
				break;
			}
		}
	}
	if(betChips == []){
		currentBet = 0;
	}else if(betChips !== []){
		currentBet = betChips.reduce(betSum);
	}	
	$('#bet-amount').html('$'+currentBet);
}
```

---

This function checks the totals for every hand present, and then goes down a huge decision tree to see if there are any blackjacks, busts, wins and losses for up to three hands. If there was a split, then the entire tree is updated accordingly based on the inclusion of the second player split hand.

```javascript
function checkWin(){
	playerTotal = calculateTotal(playersHand,'player');
	dealerTotal = calculateTotal(dealersHand,'dealer');
	splitTotal = calculateTotal(splitHand,'split');

	if(dealerTotal === 21 && dealersHand.length === 2){
		$("#dealerBlackJack").modal("show");
		// Dealer won with BlackJack...
		if(insurance){
			$("#insuranceWin").modal("show");
			bankTotal += (insuranceBet * 2);
			$('#bankAmount').html(bankTotal);
			// but player had insurance!
		}
	}else{
		if(splitTotal !== 0){
			currentBet *= 2;
			// Split played
			if(playerTotal > 21 && splitTotal > 21){
				$("#playerBusts").modal("show");
				// Player busted.
			}else if(dealerTotal > 21){
				// Dealer Busts...
				if(playerTotal <= 21 && splitTotal <= 21){
					$("#dealerBusts").modal("show");
					bankTotal += (currentBet * 2);
					$('#bankAmount').html(bankTotal);
					// and Player wins both.
				}else if(playerTotal <= 21 || splitTotal <= 21){
					$('#halfBust').modal("show");
					bankTotal += currentBet;
					$('#bankAmount').html(bankTotal);
					// and Player wins one, busts one.
				}
			}else if((playerTotal > 21 || splitTotal > 21) && dealerTotal <= 21){
				// Player busts one and Dealer stays...
				if(playerTotal > dealerTotal && splitTotal > dealerTotal){
					$("#halfBust").modal("show");
					bankTotal += currentBet;
					$('#bankAmount').html(bankTotal);
					// and Player wins one.
				}else if(playerTotal === dealerTotal || splitTotal === dealerTotal){
					$("#pushTie").modal("show");
					// and Dealer Pushes win.
				}else{
					$("#dealerWins").modal("show");
					// and dealer wins.
				}
			}else{
				// No busts...
				if(playerTotal > dealerTotal && splitTotal > dealerTotal){
					$("#playerWins").modal("show");
					bankTotal += (currentBet * 2);
					$('#bankAmount').html(bankTotal);
					// and Player wins Both.
				}else if(playerTotal <= dealerTotal && splitTotal <= dealerTotal){
					$("#dealerWins").modal("show");
					// and Dealer wins or ties both.
				}else if(playerTotal <= dealerTotal || splitTotal <= dealerTotal){
					$("#halfWin").modal("show");
					bankTotal += currentBet;
					$('#bankAmount').html(bankTotal);
					// and Player wins one.
				}
			}
		}else{
			if(playerTotal > 21){
				$("#playerBusts").modal("show");
				// Player busted.
			}else if(dealerTotal > 21){
				$("#dealerBusts").modal("show");
				bankTotal += (currentBet * 2);
				$('#bankAmount').html(bankTotal);
				// Dealer busted; player won.
			}else{
				if(playerTotal == 21 && playerTotal > dealerTotal){
					$("#playerWins").modal("show");
					bankTotal += (currentBet + ((currentBet * 3)/2));
					$('#bankAmount').html(bankTotal);
					//player won with BlackJack
				}else if(playerTotal > dealerTotal){
					$("#playerWins").modal("show");
					bankTotal += (currentBet * 2);
					$('#bankAmount').html(bankTotal);
					// player won.
				}else if(dealerTotal > playerTotal){
					$("#dealerWins").modal("show");
					// Dealer won.
				}else{
					$("#pushTie").modal("show");
					// push...no winner...default to dealer win.
				}
			}
		}
	}
	if(bankTotal <= 0){
		$("#playerLose").modal("show");
		// Out of money!
	}
	$('.reset-button').show();
}
```

---

## Screen Shots

Here are some of the states that the game can take:

![Start](https://github.com/AndyTuttle12/Blackjack/blob/master/screenshots/start-shot.png)
![Deal](https://github.com/AndyTuttle12/Blackjack/blob/master/screenshots/deal-shot.png)
![Insurance](https://github.com/AndyTuttle12/Blackjack/blob/master/screenshots/insurance-shot.png)
![Insurance Win](https://github.com/AndyTuttle12/Blackjack/blob/master/screenshots/insurance-win-shot.png)
![Split Bet](https://github.com/AndyTuttle12/Blackjack/blob/master/screenshots/split-bet-shot.png)
![Split Hit](https://github.com/AndyTuttle12/Blackjack/blob/master/screenshots/split-hit-shot.png)
![Tie End](https://github.com/AndyTuttle12/Blackjack/blob/master/screenshots/tie-shot.png)

---

## Live Demo

Try it out and win big!

[Demo](http://andytuttle.io/blackjack/)

---