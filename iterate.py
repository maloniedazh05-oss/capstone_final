import random
array = []
value = 1095

days = 0

for i in range(value):
    days = (days + 1) % 7
    if days in [1, 7]:
        array.append(0)
    else:
        array.append(random.randint(0, 10))



# create a file to write the array
with open('output3.txt', 'w') as file:
    # write the array to the file
    file.write(str(array))
print("Data created")