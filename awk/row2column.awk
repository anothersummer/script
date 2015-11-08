#!/bin/sh
a=`cat <<EOF
a	1	a1\n
a	2	a2\n
a	3	a3\n
b	1	b1\n
b	2	b2\n
b	3	b2\n
c	1	c1\n
c	2	c2\n
c	3	c2\n
EOF
`
#output example:
#a a1 a2 a3
#b b1 b2 b3
#c c1 c2 c3

#code:
echo -e $a | awk '{
	k=$1"_"$2
	a[$1];
	b[$2];
	c[k]=$3;
	
}END{
	for(a1 in a){
		printf("%s\t",a1)
	  for(b1 in b){
	    k=a1"_"b1
	    
		printf("%s\t",c[k])
	  }
	   printf("\n","")
	}
}'
