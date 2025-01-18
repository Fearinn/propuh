/**
 *------
 * BGA framework: Gregory Isabelli & Emmanuel Colin & BoardGameArena
 * propuh implementation : © Matheus Gomes matheusgomesforwork@gmail.com
 *
 * This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
 * See http://en.boardgamearena.com/#!doc/Studio for more information.
 * -----
 *
 * propuh.js
 *
 * propuh user interface script
 *
 * In this file, you are describing the logic of your user interface, in Javascript language.
 *
 */

define([
  "dojo",
  "dojo/_base/declare",
  "ebg/core/gamegui",
  "ebg/counter",
  `${g_gamethemeurl}modules/js/bga-zoom.js`,
  `${g_gamethemeurl}modules/js/bga-cards.js`,
], function (dojo, declare) {
  return declare("bgagame.propuh", ebg.core.gamegui, {
    constructor: function () {
      console.log("propuh constructor");
    },

    setup: function (gamedatas) {
      console.log("Starting game setup");

      this.pph = {
        managers: {},
        info: {},
        globals: {},
        stocks: {},
      };

      this.pph.managers.zoom = new ZoomManager({
        element: document.getElementById("pph_gameArea"),
        localStorageZoomKey: "propuh-zoom",
        zoomControls: {
          color: "black",
        },
        zoomLevels: [0.5, 0.75, 1, 1.25, 1.5],
        smooth: true,
        onZoomChange: (zoom) => {},
      });

      this.pph.managers.trick = new CardManager(this, {
        cardHeight: 209.6,
        cardWidth: 150,
        getId: (card) => `trick-${card.id}`,
        setupDiv: (card, element) => {
          element.classList.add("pph_trickCard");
        },
        setupFrontDiv: (card, element) => {
          card.type_arg = Number(card.type_arg);
          const backgroundPosition = `${card.type_arg * 100}% 0`;
          element.parentElement.parentElement.style.backgroundPosition =
            backgroundPosition;
        },
        setupBackDiv: (card, element) => {},
      });

      this.pph.stocks.deck = new Deck(
        this.pph.managers.trick,
        document.getElementById("pph_deck"),
        {
          cardNumber: 28,
          counter: {
            position: "center",
            extraClasses: "text-shadow pph_deckCounter",
          },
        }
      );

      console.log(gamedatas.hand);

      this.pph.stocks.hand = new HandStock(
        this.pph.managers.trick,
        document.getElementById("pph_hand"),
        {
          sort: (a, b) => {
            return Number(b.type_arg) - Number(a.type_arg);
          },
        }
      );

      this.pph.stocks.hand.addCards(gamedatas.hand, {}, { visible: true });

      // Setup game notifications to handle (see "setupNotifications" method below)
      this.setupNotifications();

      console.log("Ending game setup");
    },

    ///////////////////////////////////////////////////
    //// Game & client states

    // onEnteringState: this method is called each time we are entering into a new game state.
    //                  You can use this method to perform some user interface changes at this moment.
    //
    onEnteringState: function (stateName, args) {
      console.log("Entering state: " + stateName, args);

      switch (stateName) {
        case "dummy":
          break;
      }
    },

    // onLeavingState: this method is called each time we are leaving a game state.
    //                 You can use this method to perform some user interface changes at this moment.
    //
    onLeavingState: function (stateName) {
      console.log("Leaving state: " + stateName);

      switch (stateName) {
        case "dummy":
          break;
      }
    },

    // onUpdateActionButtons: in this method you can manage "action buttons" that are displayed in the
    //                        action status bar (ie: the HTML links in the status bar).
    //
    onUpdateActionButtons: function (stateName, args) {
      console.log("onUpdateActionButtons: " + stateName, args);
    },

    ///////////////////////////////////////////////////
    //// Utility methods

    ///////////////////////////////////////////////////
    //// Player's action

    onCardClick: function (card_id) {
      console.log("onCardClick", card_id);
    },

    ///////////////////////////////////////////////////
    //// Reaction to cometD notifications
    setupNotifications: function () {
      console.log("notifications subscriptions setup");
    },
  });
});
