// JavaScript is copyright Henrik Steen [henrist.net, hsw.no]


/**
 * Floating boks
 */
var FBox = new Class({
	options: {
		// opacity for boksen
		opacity: 0.95,
		
		// tid før boksen lukker seg automatisk
		delay: 300,
		
		// hvor lang tid den bruker på å fade ut
		duration: 250,
		
		// om overlay skal brukes (settes av this.overlay)
		overlay: false,
		
		// opacity for overlay boksen (settes av this.overlay)
		overlay_opacity: 0.75,
		
		// om boksen skal lukkes dersom overlay klikkes (settes av this.overlay)
		overlay_close: false
	},
	timer: false,
	
	/**
	 * Gjør at boksen lukker seg automatisk når man beveger musa utenfor
	 */
	autoclose: function(check)
	{
		if (check) return;
		
		// bytt ut funksjonen
		var self = this;
		this.autoclose = function()
		{
			self.boxw.addEvents({
				"mouseenter": function()
				{
					self.show();
				},
				"mouseleave": function()
				{
					self.hide();
				}
			});
			
			// hindre events i å bli lagt til flere ganger
			self.autoclose = $empty;
		};
		
		// finnes boksen? legg til events med en gang
		if (this.boxw)
		{
			this.autoclose();
		}
	},
	
	/**
	 * Legg til overlay rundt
	 * @param bool click_to_close - lukke boksen ved å trykke på overlay (utenfor boksen)
	 */
	overlay: function(click_to_close, opacity)
	{
		this.options.overlay = true;
		this.options.overlay_close = !!click_to_close;
		if (opacity) this.options.overlay_opacity = opacity;
	},
	
	/**
	 * Koble denne boksen til et element
	 * @param object elm
	 * @param bool show_hide - boksen blir automatisk synlig/skjult ved mus over/ut
	 * @param bool click - boksen blir kun synlig ved å klikke på elementet
	 */
	connect: function(elm, show_hide, click)
	{
		var self = this;
		
		// event for å klikke på boksen
		if (click)
		{
			elm.addEvent("click", function(event)
			{
				event.stop();
				
				// allerede synlig?
				if (self.boxw && self.boxw.getStyle("visibility") == "visible") return;
				
				self.show();
			});
		}
		
		// event for å vise/skjule boksen ved musa over/ut
		if (show_hide)
		{
			elm.addEvent("mouseenter", function()
			{
				if (!click || (self.boxw && self.boxw.getStyle("visibility") == "visible"))
				{
					self.show();
				}
			});
			elm.addEvent("mouseleave", function()
			{
				if (self.boxw && self.boxw.getStyle("visibility") == "visible")
				{
					self.hide();
				}
			});
		}
	},
	
	/**
	 * Vis (fade inn) boksen
	 */
	show: function()
	{
		// legge til overlay rundt boksen?
		if (this.options.overlay && !this.overlayobj)
		{
			var self = this;
			this.overlayobj = new Element("div", {"class": "bg_overlay", "styles": {"opacity": 0}, "tween": {"duration": this.options.duration}}).inject(document.body);
			if (this.options.overlay_close) this.overlayobj.addEvent("click", function() { self.hide(true); });
			this.overlayobj.fade(this.options.overlay_opacity);
		}
		
		$clear(this.timer);
		
		// kontrolller at boksen finnes
		if (!this.boxw)
		{
			this.create_box();
		}
		
		if (this.boxw.getStyle("opacity") == 0)
		{
			// flytt boksen til riktig posisjon
			this.move();
		}
		
		// fade inn
		this.boxw.setStyle("visibility", "visible");
		this.boxw.fade(this.options.opacity);
	},
	
	/**
	 * Skjul (fade ut) boksen
	 */
	hide: function(timer)
	{
		if (timer)
		{
			// fjerne overlay?
			if (this.overlayobj)
			{
				var ref = this.overlayobj;
				this.overlayobj.get("tween").chain(function(){ ref.destroy(); });
				this.overlayobj.fade("out");
				this.overlayobj = null;
			}
			
			// fjerne events?
			if (this.move_int)
			{
				window.removeEvent("resize", this.move_int);
				window.removeEvent("scroll", this.move_int);
			}
			
			this.boxw.fade("out");
			return;
		}
		
		var self = this;
		this.timer = setTimeout(function()
		{
			self.hide(true);
		}, this.options.delay);
	},
	
	/**
	 * Lag HTML for boksen
	 */
	create_box: function(box_elm)
	{
		if (this.box) return;
		this.box = box_elm ? box_elm : new Element("div");
		this.boxo = new Element("div", {"class": "js_box_b"}).grab(this.box);
		this.boxw = new Element("div", {"class": "js_box js_box_b", "styles": {"opacity": 0}, "tween": {"duration": this.options.duration}}).grab(new Element("div", {"class": "js_box_b"}).grab(this.boxo)).inject(document.body);
		
		// sjekk for event for å lukke boksen
		this.autoclose(true);
	},
	
	/**
	 * Flytt boksen
	 */
	pos_x: "center", // left, center, right
	pos_y: "center", // top, center, bottom
	rel_x: "window", // window, <element>
	rel_y: "window", // window, <element>, x
	offset_x: 0, // [width, center, neg, <integer>]
	offset_y: 0, // [height, center, neg, <integer>]
	outer: window, // ramme som boksen må være inni
	outer_space: [5, 5, 5, 5], // avstand fra kantene
	move: function()
	{
		// fjern gamle events
		if (this.move_int)
		{
			window.removeEvent("resize", this.move_int);
			window.removeEvent("scroll", this.move_int);
		}
		
		// funksjonen som tar seg av flyttingen
		// (for å kunne legge til som events)
		var self = this;
		this.move_int = function()
		{
			// hent variabler
			offset_x = $splat(self.offset_x);
			offset_y = $splat(self.offset_y);
			var size_box = self.boxw.getSize();
			
			// relativobjekt
			var rel_xo = (self.rel_x == "window" ? window : self.rel_x);
			var rel_yo = (self.rel_y == "window" ? window : (self.rel_y == "x" ? rel_xo : self.rel_y));
			
			// posisjonsobjekt for rel x/y
			var pos_xo = (self.rel_x == "window" ? {x:0,y:0} : self.rel_x.getPosition());
			var pos_yo = (self.rel_x == self.rel_y ? pos_xo : (self.rel_y == "x" ? pos_xo : (self.rel_y == "window" ? {x:0,y:0} : self.rel_y.getPosition())));
			
			// offset for rel x/y
			var size_x = (self.pos_x == "left" ? 0 : (self.pos_x == "center" ? rel_xo.getSize().x/2 - size_box.x/2 : rel_xo.getSize().x - size_box.x));
			var size_y = (self.pos_y == "top" ? 0 : (self.pos_y == "center" ? rel_yo.getSize().y/2 - size_box.y/2 : rel_yo.getSize().y - size_box.y));
			
			// finn x-verdien
			var x = pos_xo.x + size_x;
			var y = pos_yo.y + size_y;
			
			// sjekk scroll
			var scroll = (self.rel_x == "window" || self.rel_y == "window" ? window.getScroll() : false);
			if (self.rel_x == "window")
			{
				x += scroll.x;
			}
			if (self.rel_y == "window")
			{
				y += scroll.y;
			}
			
			// legg til offset
			var offset_x_val = 0;
			offset_x.each(function(item)
			{
				if (item == "width") offset_x_val += rel_xo.getSize().x;
				else if (item == "center") offset_x_val += rel_xo.getSize().x/2;
				else if (item == "neg") offset_x_val *= -1;
				else offset_x_val += item;
			});
			var offset_y_val = 0;
			offset_y.each(function(item)
			{
				if (item == "height") offset_y_val += rel_yo.getSize().y;
				else if (item == "center") offset_y_val += rel_yo.getSize().y/2;
				else if (item == "neg") offset_y_val *= -1;
				else offset_y_val += item;
			});
			
			x += offset_x_val;
			y += offset_y_val;
			
			outer_pos = self.outer.getPosition();
			outer_size = self.outer.getSize();
			
			// plasser boksen
			self.boxw.setStyles({
				"left": 0,
				"right": "auto",
				"top": Math.max(outer_pos.y+self.outer_space[0], y)
			});
			
			size_box = self.boxw.getSize();
			
			// sørg for at boksen ikke går utenfor høyre side
			var right = outer_pos.x + outer_size.x + self.outer.getScroll().x - self.outer_space[1];
			if (x + size_box.x > right)
			{
				// sett right verdi i stedet
				self.boxw.setStyles({
					"right": window.getSize().x-right,
					"left": "auto"
				});
			}
			
			else
			{
				self.boxw.setStyle("left", Math.max(self.outer.getPosition().x+self.outer_space[3], x));
			}
		};
		
		// legg til events (ikke resize i IE)
		if (!Browser.Engine.trident) window.addEvent("resize", this.move_int);
		if (this.rel_x == "window" || this.rel_y == "window" || true)
		{
			this.eventScroll = window.addEvent("scroll", this.move_int);
		}
		
		// utfør flytting
		this.move_int();
	},
	
	/**
	 * Oppdatere data
	 * @param string or element data
	 */
	populate: function(data)
	{
		if ($type(data) == "string") this.box.set("html", data);
		else this.box.empty().grab(data);
		
		// flytt boksen på nytt i tilfelle innholdet har strukket boksen
		if (this.move_int) this.move_int();// else this.move();
	}
});