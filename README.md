KMDG-Job-Board v1.01
====================

Minimalist, color-coordinated PHP / MySQL / jQuery / AJAX job board

Dependencies
------------
o jQuery
o jQuery UI (Core, Datepicker, Effects Core)

We made a digital job board to replace our worn-out dry erase board around the office,
and we're pretty darn proud. So proud, in fact, that we're going to share it with the
world!

The job board we made is usable, sure—but the important part is that it's fun. It's
all AJAX-powered, so you can edit it without any page refreshes, and it even sports a
few subtle animations to make it application-like. It's also click-to edit. It's
dependent on jQuery (what isn't), and also needs the jQuery UI library for the
datepicker as well as to help it out with some of the animations. It also uses SVG
icons, so it's scalable on any display.

But all this talk and no demo! Where are my manners?

Demo: @link http://sandbox.kmdg.com/job-board/

More Info
---------

This is essentially a two-file application: one PHP script, and one JavaScript file.
It's technically three files plus images, as it uses a PHP database class that sits in
its own file. The structure is more or less straightforward, but the update system is
worth talking about.

The way it updates is: jQuery will replace certain items with inputs following an
onClick event. The inputs follow this format:

o class - This attribute determines the database column to be updated
o rel - This attribute gives the ID to be updated
o value - This attribute sets the value to update the entry

Once there has been an onBlur event called or the Enter key is pressed, a POST request
is sent to itself, and it will update any input fields it finds with this information
using the class, rel, and value attributes to enter into the database. Sure, HTML5 data
attributes could have been used, but it sure as heck cuts down on the markup if you can
reuse attributes for CSS and JavaScript without shooting yourself in the foot. I think
I avoided that here.

Also, at KMDG we've recently done away with inline $(document).ready() code in our
jQuery, and have opted for all external JS files (which don't need this if loaded right
before the </body> tag).