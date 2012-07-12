====================================
Question2Answer Best Users per Month v1.1
====================================
-----------
Description
-----------
This is a plugin for **Question2Answer** that displays the best users of the current month in a widget and on a separate page

--------
Features
--------
- displays a widget that holds the best users of the current month regarding their stored userpoints
- provides a page for showing best users of previous months, access-URL ``your-q2a-installation.com/bestusers``
- the plugin creates a table "qa_userscores" in your database to store monthly userpoints
- you need to setup a cronjob for file "cronjob.php" that is called the first day of each month, so that monthly userscores get stored

------------
Example
------------
This plugin is used at www.gute-mathe-fragen.de (q2a-forum for mathematics). See also screenshot in the plugin files.

------------
Installation
------------
#. Install Question2Answer_
#. Get the source code for this plugin directly from github_
#. Extract the files.
#. Change language strings in file **qa-best-users-per-month-lang.php**
#. Optional: Change settings in file qa-best-users-per-month-widget.php and qa-best-users-per-month-page.php
#. Upload the files to a subfolder called ``best-users-per-month`` inside the ``qa-plugins`` folder of your Q2A installation.
#. Navigate to your site, go to **Admin -> Plugins** on your q2a install. Check if plugin "Best Users per Month" is listed. This will automatically install the table qa_userscores.
#. Then go to **Admin >Layout >Available widgets**, and add the widget "Best Users per Month", set its position to: Side panel - Below sidebar box
#. Setup a cronjob_ for file ``qa-plugin/best-users-per-month/cronjob/cronjob.php``, and call it the first day of each month (it stores the monthly userpoints into table qa_userscores)
#. Run **cronjob.php** once. This will save all recent userpoints as userscores. Do not wonder: Afterwards all userscores will start with 0 points.

.. _Question2Answer: http://www.question2answer.org/install.php
.. _github: https://github.com/echteinfachtv/q2a-best-users-per-month
.. _cronjob: http://www.question2answer.org/qa/16425/new-plugin-best-users-per-month-release-call-for-beta-users?show=16443#a16443

----------
Disclaimer
----------
This is **beta** code. It is probably okay for production environments, but may not work exactly as expected. You bear the risk. Refunds will not be given!

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
See the GNU General Public License for more details.

-------
Copyright
-------
All code herein is OpenSource_. Feel free to build upon it and share with the world.

.. _OpenSource: http://www.gnu.org/licenses/gpl.html

---------
About q2a
---------
Question2Answer is a free and open source platform for Q&A sites. For more information, visit:

http://www.question2answer.org/

---------
Final Note
---------
If you use the plugin:
#. Consider joining the Question2Answer-Forum_, answer some questions or write your own plugin!
#. You can use the code of this plugin to learn more about q2a-plugins. It is commented code.
#. Thanks!

.. _Question2Answer-Forum: http://www.question2answer.org/qa/
