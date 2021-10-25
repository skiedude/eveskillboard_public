# EveSkillboard Public Core Files
This is not meant to be the complete source code of EveSkillboard. It is however meant to share the core, more complicated functionality that will speed up development of a replacement.  
I will try to comment in the files the best I can to help explain how the functions work.  
Again I want to thank the community for supporting my website for the years it was up.  
While some may not agree with me not releasing all the code, I thought this was a good alternative to get the core features out there.  
My formatting, naming and coding skills are probably scary to some, but alas, here we are. I always meant to rewrite it, and I even started on that a few months ago, but that quickly lost steam as I no longer played eve.  

## Background  
EveSkillboard was initially built in a day while I learned PHP. I did not have all the cool bells and whistles in that first day, just the core functionality to show skills.  
Laravel was the underlying framework, it made resolving and installing dependencies easy and some nice to have features.  
MySQL was the backend database, however that was primarily just used to store basic character information, tokens, static skill mapping data. 99% of the data was stored using Laravels FileCache backend, and the data was stored in objects.  


## Skills.php
Despite the name, this turned into a catch-all for the majority of calls to ESI (standings, public_info, implants, attributes, history, jump_clones, skill_in_training, skills).  
It has a handful of helper functions and one main giant function.  

## ShipData.php
This is the magic behind calculating what ships a character can fly, calculating the time left to train for every ship and the missing skills of every ship  
Kudos to u/evanova on r/evetech 4 years ago with starting me out with the [magic query](https://www.reddit.com/r/evetech/comments/7p8p38/ships_a_character_can_fly/) 

## board.blade.php
HTML template to build the main skillboard with the fancy boxes. This is one of the places the giant cached objects were passed to for lots of looping  

## ships.blade.php
HTML template to build the ships tab that showed what you can fly, cannot and how long to train each ship  
