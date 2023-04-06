/** @module misc/debug */

// Add grid.
var style = {
    grid: `
                position: fixed;
                top: 0;
                right: 0;
                bottom: 0;
                left: 0;
                z-index: 9999;
                display: flex;
                justify-content: center;
            `,
    container: `
                height: 100%;
            `,
    row: `
                height: 100%;
            `,
    col: `
                width: 100%;
                height: 100%;
            `,
    content: `
                width: 100%;
                height: 100%;
                background-color: rgba(0, 255, 255, 0.25);
            `,
};
var $grid = $(`
            <div class="design-grid" style="${style['grid']}">
                <div class="o-container" style="${style['container']}">
                    <div class="o-row" style="${style['row']}">
                        <div class="o-col" style="${style['col']}"><div style="${style['content']}"></div></div>
                        <div class="o-col" style="${style['col']}"><div style="${style['content']}"></div></div>
                        <div class="o-col" style="${style['col']}"><div style="${style['content']}"></div></div>
                        <div class="o-col" style="${style['col']}"><div style="${style['content']}"></div></div>
                        <div class="o-col" style="${style['col']}"><div style="${style['content']}"></div></div>
                        <div class="o-col" style="${style['col']}"><div style="${style['content']}"></div></div>
                        <div class="o-col" style="${style['col']}"><div style="${style['content']}"></div></div>
                        <div class="o-col" style="${style['col']}"><div style="${style['content']}"></div></div>
                        <div class="o-col" style="${style['col']}"><div style="${style['content']}"></div></div>
                        <div class="o-col" style="${style['col']}"><div style="${style['content']}"></div></div>
                        <div class="o-col" style="${style['col']}"><div style="${style['content']}"></div></div>
                        <div class="o-col" style="${style['col']}"><div style="${style['content']}"></div></div>
                    </div>
                </div>
            </div>'
        `).on({
    click: function (e) {
        $(e.currentTarget).toggle();
    },
});

$grid.toggle();
$('body').append($grid);
$('html').on({
    dblclick: function (e) {
        $grid.toggle();
    },
});
