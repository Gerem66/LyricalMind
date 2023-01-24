from os import system
from time import sleep

# Read the file from lyrics-sync.py
f = open('./tmp/juice-voice.wav/data1.txt', 'r')
lines = f.readlines()
f.close()

# Get the data & lyrics
data = [ line.replace('\n', '') for line in lines ]
lyrics = data[0].split(',')[1:]     # Keep lyrics from the first line
data = data[1:]                     # Remove the first line

# Get the sum of each line to find start and end
def getSum(line): return sum(list(map(int, data[line].split(',')[1:])))
timerSum = [ getSum(i) for i in range(len(data)) ]

step = .2
suite = 3
tolerance = 1200
startIndex = 0
endIndex = len(data) - 1

# Find the first index
for i in range(suite, len(timerSum)):
    if sum(timerSum[i - suite:i]) / suite > tolerance:
        startIndex = i - suite
        break

# Find the last index
for i in range(suite, len(timerSum)):
    if sum(timerSum[-i - suite:-i]) / suite > tolerance:
        endIndex = len(timerSum) - i
        break

# Create default values for timecodes & convert them to seconds
timecodes = list(range(startIndex, endIndex, int((endIndex - startIndex) / len(lyrics))))[:len(lyrics)]
timecodes_seconds = [ round(i * step, 2) for i in timecodes ]

def GetLyricsRecognition(index):
    return [ float(line.split(',')[index + 1]) for line in data ]

# Passer sur tous les timecodes et vérifier dans les data la meilleure correspondance (avec limite) tout en gardant l'ordre des paroles (entre [min|précédant, max|suiant])
# Try to find better values for timecodes
offset = int(3 / step)
tolerance = 40
copyTimecodes = timecodes.copy()
for lineIndex in range(len(lyrics)):
    lineTimecode = timecodes[lineIndex]
    lineAllTimecodes = GetLyricsRecognition(lineIndex)

    minIndex = max(0,                         lineTimecode - offset)
    maxIndex = min(len(lineAllTimecodes) - 1, lineTimecode + offset)
    lineAllTimecodes = lineAllTimecodes[minIndex:maxIndex]

    maxVal = max(lineAllTimecodes)
    maxValIndex = lineAllTimecodes.index(maxVal)
    #print('Line: {} - Min index: {} - Max index: {} - Max value: {} - Max value index: {}'.format(lineIndex, minIndex, maxIndex, maxVal, maxValIndex))

    if maxVal > tolerance:
        #timecodes[lineIndex] = minIndex + maxValIndex
        copyTimecodes[lineIndex] = minIndex + maxValIndex
copyTimecodesSeconds = [ round(i * step, 2) for i in copyTimecodes ]

# Print the results
print('Start index: {:.2f} ({})'.format(startIndex * step, startIndex))
print('End index: {:.2f} ({})'.format(endIndex * step, endIndex))
print('Total data: {} - Final size: {} - Lyrics length: {} - Step size: {}'.format(len(data), endIndex - startIndex, len(lyrics) - 1, int((endIndex - startIndex) / (len(lyrics) - 1))))
print('Timecodes: {}\nTimecodes seconds: {}'.format(timecodes, timecodes_seconds))
print('Copy timecodes: {}\nCopy timecodes seconds: {}'.format(copyTimecodes, copyTimecodesSeconds))

exit()

for i in range(len(timerSum) - 200):
    print('[{}] {}: {}'.format(i, round(i * step, 2), timerSum[i]))

exit()

for i in range(len(data)):
    system('clear')
    time = data[i].split(',')[0]
    cells = list(map(int, data[i].split(',')[1:]))
    print(time)
    print(cells)
    print('Total: ' + str(len(cells)))
    print('Max: ' + str(max(cells)) + ' at ' + str(cells.index(max(cells))))
    print('Sentence: ' + lyrics[cells.index(max(cells))])
    sleep(1)
