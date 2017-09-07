<h3>1. Upload a file</h3>
    <p>
        First thing you need to do is to upload a file.
        Best Import handles XML files, but you can also upload CSV file and it will be automatically converted to XML.
        Additionally you can upload ZIP file containing XML file and pictures.
        Then you will be also able to import the pictures to WordPress  (<a href="http://zefirstudio.pl/wp-best-import/">pro only</a>).
    </p>

<h3>2. See the structure</h3>
    <p>
        You may want to see the structure of the XML file.
        Therefore, in this tab, you have the preview of all the data contained in the file.
        Take a note to tags, subtags and attributes, because you will use them in the data section.
    </p>

    <h4>Tags</h4>
    <p>
        <code>&lt;tag/subtag&gt;</code> standard tag<br>
        <code>&lt;tag/subtag&gt;[attribute]</code> tag with an attribute<br>
        <code>&lt;tag/subtag&gt;[attribute1][attribute2]...</code> tag with multiple attributes<br>
    </p>

    <h4>Attributes</h4>
    <p>
    <code>[name=value]</code> the attribute is equal to the value<br>
        <code>[name!=value]</code> the attribute is not equat to the value<br>
        <code>[name^=value]</code> the attribute starts with the value<br>
        <code>[name$=value]</code> the attribute ends with the value<br>
        <code>[name*=value]</code> the attribute contains the value<br>
        <code>[name&lt;value]</code> the attribute is less than the value<br>
        <code>[name&lt;=value]</code> the attribute is less than or equal to the value<br>
        <code>[name&gt;value]</code> the attribute is greater than the value<br>
        <code>[name&gt;=value]</code> the attribute is greater than or equal to the value<br>
    </p>

    <h4>Examples</h4>
    <p>
        <code>&lt;offers/row&gt;[name=photo][width>1024]</code><br>
        <code>&lt;library/book&gt;[title*=harry]</code><br>
    </p>

<h3>3. Fill in the form</h3>
    <p>
        The most important step is filling the form with the appropriate tags and attributes as it was described above.
        To make work quite easier, the tags and attributes will be suggested during typing in the fields.
    </p>

    <h4>Data</h4>
    <p>
        Main tag - the tag you want to import, Best Import will know how many rows should be imported.<br>
        Type - the type of the post from one available in your theme.<br>
        Title - the title of the post.<br>
        Content - the content of the post.<br>
        Date - the date of the post.
    </p>

    <h4>Taxonomies</h4>
    <p>
        Taxonomies are automatically detected depending on what type of the post you selected.
    </p>

    <h4>Media (<a href="http://zefirstudio.pl/wp-best-import/">pro only</a>)</h4>
    <p>
        The images that will be added to your post.
        If the images are already on the server in /wp-content/uploads/ select first option.
        If the images are already on the server in /wp-content/uploads/directory/ select first option and preceed the text in the Media field with directory/.
        If the data contains links to images and they need to be uploaded on the server, select second option.
    </p>

    <h4>Templates</h4>
    <p>
        If you have filled in the form and want to use it again later, the templates will help you.
        You can save/load/remove all the information you provided here.
        Additionally, you will get the link to the template and will be able to add it to your CRON to schedule importing the data.
    </p>

<h3>4. Refine the data</h3>
    <p>
        It's not obligatory to even look here, but sometimes more advanced options are necessary to do some more complicated tasks.
    </p>

    <h4>Custom fields (<a href="http://zefirstudio.pl/wp-best-import/">pro only</a>)</h4>
    <p>
        The title, the content and the date is usually not sufficient.
        You often need to provide additional information, for example. price and weight for products.
        Click on the Decect button to find all custom fields that are available in your theme.
        You can also add your own fields and it will be saved as post meta data.
    </p>

    <h4>Filtering (<a href="http://zefirstudio.pl/wp-best-import/">pro only</a>)</h4>
    <p>
        You may want to filter the data.
        For example, if you wish to add only products that are cheaper than 100$, set the appropriate filter.
        Moreover, you can set when the post is added, skipped, updated or deleted.
    </p>

    <h4>Mapping</h4>
    <p>
        Sometimes you need to translate some values into the others.
        For example, in your shop, there is category named Adventure Books, but in your XML file it's named Adventure Story.
        If so, just add mapping for Categories from one name to another.
    </p>


<h3>5. Import the data</h3>
    <p>
        When you have finished previous steps, now you can see the results.
        You don't have to import all the data, you can specify how many rows you want to add.
        If you leave the field empty, then all the data will be imported.
        The last tab contains the preview of the data.
        Make sure that everything looks like you except.
        If not, open previous tab and correct the data.
        But if everything looks fine, just click on the Import button and enjoy your job being done.
    </p>