/** @module utils/shuffle */


/**
 * Creates an array of shuffled values, using a version of the Fisher-Yates shuffle.
 *
 * @param {Array} array - The array to shuffle.
 * @returns {Array} New shuffled array.
 */
export default function shuffle(array) {
    const shuffledArray = array.slice(0);
    let currentIndex = shuffledArray.length;
    let randomIndex;
    let temporaryValue;

    // While there remain elements to shuffle...
    while (currentIndex !== 0) {
        // Pick a remaining element...
        randomIndex = Math.floor(Math.random() * currentIndex);
        currentIndex -= 1;

        // And swap it with the current element.
        temporaryValue = shuffledArray[currentIndex];
        shuffledArray[currentIndex] = shuffledArray[randomIndex];
        shuffledArray[randomIndex] = temporaryValue;
    }

    return shuffledArray;
}
