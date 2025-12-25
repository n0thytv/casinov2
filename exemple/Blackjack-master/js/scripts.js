// ----------GLOBALS----------
var theDeck = createDeck();
var playersHand = [];
var dealersHand = [];
var splitHand = [];
var topOfDeck = 4;
var hiddenDealerCard;
var handSize = 6;
var setHTML = '';
var currentBet = 0;
var betChips = [];
var bankTotal = 1000;
var insurance = false;
var insuranceBet = 0;
// ---------------------------

$(document).ready(function(){
	
	buildDivs();
	$('.activeChip').draggable({
		containment: '#the-table',
		cursor: 'pointer',
		cursorAt: { top: 18, left: 29 },
		helper: "clone",
		revert: true
	});
	$('#dropArea').droppable({
		tolerance: 'touch',
		drop: droppedChip
	});
	$('.deal-button').attr('disabled', 'disabled');
	$('.hit-button').attr('disabled', 'disabled');
	$('.stand-button').attr('disabled', 'disabled');
	$('.double-button').attr('disabled', 'disabled');
	$('.split-button').hide();
	$('.reset-button').hide();
	$('.split-group').hide();
	$('.bet-amount').hide();
	$('.split-total').hide();
	$('.split-amount').hide();
	$('.insurance-button').hide();
	$('.insurance-amount').hide();

	// Major Buttons for the game
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
	$('.hit-button').click(function(){
		$('.dealt1').removeClass('dealt1');
		$('.dealt2').removeClass('dealt2');
		$('.dDealt1').removeClass('dDealt1');
		$('.dDealt2').removeClass('dDealt2');
		$('.insurance-button').hide();
		if(calculateTotal(playersHand,'player') < 21){
			// add a card to js and document; update total
			$('.double-button').attr('disabled', 'disabled');
			playersHand.push(theDeck.shift());
			$('.player-cards .card-3').addClass('dealt3');
			var slotForNewCard = playersHand.length;
			placeCard('player',slotForNewCard,playersHand[playersHand.length-1]);
			if(calculateTotal(playersHand, 'player') >= 21){
				stand();
			}
		}
	});
	$('.stand-button').click(function(){
		stand();
	});
	function stand(){
		$('.split-group').hide();
		$('.insurance-button').hide();
		var dealerTotal = calculateTotal(dealersHand,'dealer');
		$('.dealer-cards .card-2').html('<img src="images/' + hiddenDealerCard + '.png">');
		while(dealerTotal < 17){
			dealersHand.push(theDeck.shift());
			var slotForNewCard = dealersHand.length;
			$('.dealer-cards .card-'+slotForNewCard+'').addClass('dealerDealt'+slotForNewCard+'');
			placeCard('dealer',slotForNewCard,dealersHand[dealersHand.length-1]);
			dealerTotal = calculateTotal(dealersHand, 'dealer');
		}
		$('.stand-button').attr('disabled', 'disabled');
		$('.hit-button').attr('disabled', 'disabled');
		$('.double-button').attr('disabled', 'disabled');
		$('.dealer-total-number').show();
		checkWin();
	};
	$('.double-button').click(function(){
		bankTotal -= currentBet;
		$('#bankAmount').html(bankTotal);
		currentBet *= 2; 
		$('#bet-amount').html('$'+currentBet);
		$('.double-button').attr('disabled', 'disabled');
		$('.dealt1').removeClass('dealt1');
		$('.dealt2').removeClass('dealt2');
		$('.dDealt1').removeClass('dDealt1');
		$('.dDealt2').removeClass('dDealt2');
		$('.insurance-button').hide();
		if(calculateTotal(playersHand,'player') <= 21){
			// add a card to js and document; update total
			playersHand.push(theDeck.shift());
			$('.player-cards .card-3').addClass('dealt3');
			var slotForNewCard = playersHand.length;
			placeCard('player',slotForNewCard,playersHand[playersHand.length-1]);
			if(calculateTotal(playersHand, 'player') >= 21){
				stand();
			}
		}
	});
	$('.split-button').click(function(){
		bankTotal -= currentBet;
		$('#bankAmount').html(bankTotal);
		$('.split-button').hide();
		$('.split-group').show();
		$('.hit-left').show();
		$('.hit-right').show();
		$('.split-total').show();
		$('#split-amount').html('$'+currentBet);
		$('.split-amount').show();
		$('.double-button').attr('disabled', 'disabled');
		$('.hit-button').attr('disabled', 'disabled');
		$('.player-cards .card-1').addClass('splitLeft');
		$('.player-cards .card-2').addClass('card-1 splitRight').removeClass('card-2 dealt2').hide().removeClass('card-1 splitRight').addClass('card-2');
		$('.split-cards .card-1').addClass('dealt2 splitDealt1');
		$('.insurance-button').hide();
		splitHand.push(playersHand.pop());
		var slotForNewCard = splitHand.length;
		placeCard('split',slotForNewCard,splitHand[splitHand.length-1]);
		calculateTotal(playersHand, 'player');
		calculateTotal(splitHand, 'split');
	});
	$('.hit-left').click(function(){
		$('.dealt1').removeClass('dealt1');
		$('.dealt2').removeClass('dealt2');
		$('.dealt3').removeClass('dealt3');
		$('.dealt4').removeClass('dealt4');
		$('.dealt5').removeClass('dealt5');
		$('.dealt6').removeClass('dealt6');
		$('.dDealt1').removeClass('dDealt1');
		$('.dDealt2').removeClass('dDealt2');
		if(calculateTotal(playersHand,'player') < 21){
			// add a card to js and document; update total
			$('.double-button').attr('disabled', 'disabled');
			playersHand.push(theDeck.shift());
			var slotForNewCard = playersHand.length;
			$('.player-cards .card-'+slotForNewCard+'').addClass('splitLeft dealt'+slotForNewCard+'').show();
			placeCard('player',slotForNewCard,playersHand[playersHand.length-1]);
			console.log(playersHand)
			calculateTotal(playersHand, 'player');
			if(calculateTotal(splitHand, 'split') >= 21 && calculateTotal(playersHand, 'player') >= 21){
				stand();
			}
		}
	});
	$('.hit-right').click(function(){
		$('.dealt1').removeClass('dealt1');
		$('.dealt2').removeClass('dealt2');
		$('.dDealt1').removeClass('dDealt1');
		$('.dDealt2').removeClass('dDealt2');

		if(calculateTotal(splitHand,'split') < 21){
			// add a card to js and document; update total
			$('.double-button').attr('disabled', 'disabled');
			splitHand.push(theDeck.shift());
			var slotForNewCard = splitHand.length;
			$('.split-cards .card-'+slotForNewCard+'').addClass('splitDealt'+slotForNewCard+'').show();
			placeCard('split',slotForNewCard,splitHand[splitHand.length-1]);
			calculateTotal(splitHand, 'split');
			if(calculateTotal(splitHand, 'split') >= 21 && calculateTotal(playersHand, 'player') >= 21){
				stand();
			}
		}
	});
	$('.insurance-button').click(function(){
		bankTotal -= currentBet;
		$('#bankAmount').html(bankTotal);
		insurance = true;
		insuranceBet = currentBet;
		$('.insurance-button').hide();
		$('#insurance-amount').html('$'+currentBet);
		$('.insurance-amount').show();
	});
	$('#bet-reset').click(function(){
		reset();
	});
	$('.reset-button').click(function(){
		reset();
		$('.reset-button').hide();
	});
});

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

function betSum(runningTotal, number) {
    return runningTotal + number;
}

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

function checkBlackJack(){
	playerTotal = calculateTotal(playersHand,'player');
	dealerTotal = calculateTotal(dealersHand,'dealer');
	if(playerTotal == 21){
		checkWin();
		$('#playerBlackJack').modal("show");
		$('.reset-button').show();
		$('.deal-button').attr('disabled', 'disabled');
		$('.hit-button').attr('disabled', 'disabled');
		$('.stand-button').attr('disabled', 'disabled');
		$('.double-button').attr('disabled', 'disabled');
	}
}

function reset(){
	// reset hands and deck...and the DOM
	theDeck = createDeck();
	playersHand = [];
	dealersHand = [];
	splitHand = [];
	$('.card').html('');
	$('.split-total').hide();
	// reset bet chips
	$('#dropArea').empty();
	betChips = [0];
	calculateBet();
	betChips = [];
	$('.deal-button').attr('disabled', 'disabled');
	$('.hit-button').attr('disabled', 'disabled');
	$('.stand-button').attr('disabled', 'disabled');
	$('.double-button').attr('disabled', 'disabled');
	console.log($('.deal-button'));
	$('#fiveChip').html('<img id="five1" class="activeChip fiveChip" src="images/5Chip.png">'+
						'<img id="five2" class="activeChip fiveChip" src="images/5Chip.png">'+
						'<img id="five3" class="activeChip fiveChip" src="images/5Chip.png">'+
						'<img id="five4" class="activeChip fiveChip" src="images/5Chip.png">'+
						'<img id="five5" class="activeChip fiveChip" src="images/5Chip.png">'+
						'<img id="five6" class="activeChip fiveChip" src="images/5Chip.png">'+
						'<img id="five7" class="activeChip fiveChip" src="images/5Chip.png">'+
						'<img id="five8" class="activeChip fiveChip" src="images/5Chip.png">'+
						'<img id="five9" class="activeChip fiveChip" src="images/5Chip.png">'+
						'<img id="five10" class="activeChip fiveChip" src="images/5Chip.png">'+
						'<div id="fiveChips">'+
							'<img src="images/fiveChips.png">'+
						'</div>');
	$('#tenChip').html('<img id="ten1" class="activeChip tenChip" src="images/10Chip.png">'+
						'<img id="ten2" class="activeChip tenChip" src="images/10Chip.png">'+
						'<img id="ten3" class="activeChip tenChip" src="images/10Chip.png">'+
						'<img id="ten4" class="activeChip tenChip" src="images/10Chip.png">'+
						'<img id="ten5" class="activeChip tenChip" src="images/10Chip.png">'+
						'<img id="ten6" class="activeChip tenChip" src="images/10Chip.png">'+
						'<img id="ten7" class="activeChip tenChip" src="images/10Chip.png">'+
						'<img id="ten8" class="activeChip tenChip" src="images/10Chip.png">'+
						'<img id="ten9" class="activeChip tenChip" src="images/10Chip.png">'+
						'<img id="ten10" class="activeChip tenChip" src="images/10Chip.png">'+
						'<div id="tenChips">'+
							'<img src="images/tenChips.png">'+
						'</div>');
	$('#twentyFiveChip').html('<img id="twentyFive1" class="activeChip twentyFiveChip" src="images/25Chip.png">'+
						'<img id="twentyFive2" class="activeChip twentyFiveChip" src="images/25Chip.png">'+
						'<img id="twentyFive3" class="activeChip twentyFiveChip" src="images/25Chip.png">'+
						'<img id="twentyFive4" class="activeChip twentyFiveChip" src="images/25Chip.png">'+
						'<img id="twentyFive5" class="activeChip twentyFiveChip" src="images/25Chip.png">'+
						'<img id="twentyFive6" class="activeChip twentyFiveChip" src="images/25Chip.png">'+
						'<img id="twentyFive7" class="activeChip twentyFiveChip" src="images/25Chip.png">'+
						'<img id="twentyFive8" class="activeChip twentyFiveChip" src="images/25Chip.png">'+
						'<img id="twentyFive9" class="activeChip twentyFiveChip" src="images/25Chip.png">'+
						'<img id="twentyFive10" class="activeChip twentyFiveChip" src="images/25Chip.png">'+
						'<div id="twentyFiveChips">'+
							'<img src="images/twentyFiveChips.png">'+
						'</div>');
	$('#hundredChip').html('<img id="hundred1" class="activeChip hundredChip" src="images/100Chip.png">'+
						'<img id="hundred2" class="activeChip hundredChip" src="images/100Chip.png">'+
						'<img id="hundred3" class="activeChip hundredChip" src="images/100Chip.png">'+
						'<img id="hundred4" class="activeChip hundredChip" src="images/100Chip.png">'+
						'<img id="hundred5" class="activeChip hundredChip" src="images/100Chip.png">'+
						'<img id="hundred6" class="activeChip hundredChip" src="images/100Chip.png">'+
						'<img id="hundred7" class="activeChip hundredChip" src="images/100Chip.png">'+
						'<img id="hundred8" class="activeChip hundredChip" src="images/100Chip.png">'+
						'<img id="hundred9" class="activeChip hundredChip" src="images/100Chip.png">'+
						'<img id="hundred10" class="activeChip hundredChip" src="images/100Chip.png">'+
						'<div id="hundredChips">'+
							'<img src="images/hundredChips.png">'+
						'</div>');
	$('.dealt1').removeClass('dealt1');
	$('.dealt2').removeClass('dealt2');
	$('.dDealt1').removeClass('dDealt1');
	$('.dDealt2').removeClass('dDealt2');
	$('.bet-amount').hide();
	$('.split-amount').hide();
	$('.insurance-amount').hide();
	$('.insurance-button').hide();
	playerTotal = calculateTotal(playersHand,'player');
	dealerTotal = calculateTotal(dealersHand,'dealer');
	buildDivs();
	$('.activeChip').draggable({
		containment: '#the-table',
		cursor: 'pointer',
		cursorAt: { top: 18, left: 29 },
		helper: "clone",
		revert: true
	});
	$('#dropArea').droppable({
		tolerance: 'touch',
		drop: droppedChip
	});
}

function createDeck(){
	var newDeck = [];
	var suits = ['h','s','d','c'];
	for(let s = 0; s < suits.length; s++){
		for(let c = 1; c <= 13; c++){
			newDeck.push(c+suits[s]);
		}
	}
	return newDeck;
}

function shuffleDeck(){
	for(let i = 0; i < 9001; i++){
		var random1 = Math.floor(Math.random()*theDeck.length);
		var random2 = Math.floor(Math.random()*theDeck.length);
		var temp = theDeck[random1];
		theDeck[random1] = theDeck[random2];
		theDeck[random2] = temp;
	}
}

function placeCard(who, where, whatCard){
	var classSelector = '.' + who + '-cards .card-' + where + ' .card-container .card-front';
	var classSelector2 = '.' + who + '-cards .card-' + where + ' .card-container';
	// example = .dealer-cards .card-1 .card-container .card-front
	$('.dealer-total-number').hide();
	$(classSelector).html('<img src="images/' + whatCard + '.png">');
	if(classSelector == '.dealer-cards .card-2 .card-container .card-front'){
		$(classSelector).html('<img src="images/deck.png">');
		$('.deal-button').attr('disabled', 'disabled');
	}
	$(classSelector2).toggleClass('flip');
}

function calculateTotal(hand, who){
	var total = 0; //running total
	var cardValue = 0; //temp value of card
	var hasAce = false; //ace counter
	for(let i = 0; i < hand.length; i++){
		cardValue = Number(hand[i].slice(0,-1));
		if(cardValue > 10){
			cardValue = 10;
		}
		if(cardValue == 1){
			hasAce = true;
		}
		if(cardValue === 1 && total <= 10){
			cardValue = 11;
		}
		total += cardValue;
		if((total > 21)&&(hasAce)){
            total -= 10;
            hasAce = false;
        }
	}
	var classSelector = '.' + who + '-total-number';
	$(classSelector).text(total);
	return total;
}

function buildDivs(){
	for(let i = 1; i <= handSize; i++){
		setHTML += '<div class="col-sm-2 card card-' + i + '">';
			setHTML += '<div class="card-container">';
				setHTML += '<div class="card-front"></div>';
				setHTML += '<div class="card-back"></div>';
			setHTML += '</div>';
		setHTML += '</div>';
	}
	$('.dealer-cards').html(setHTML);
	$('.player-cards').html(setHTML);
	$('.split-cards').html(setHTML);
}

$('#myModal').on('show.bs.modal', function (e) {
  if (!data) return e.preventDefault() // stops modal from being shown
})

