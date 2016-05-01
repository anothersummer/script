import multiprocessing
import time
def func(msg):
  for i in xrange(3):
    print msg
    time.sleep(1)
  return "done " + msg
if __name__ == "__main__":
  pool = multiprocessing.Pool(processes=4)
  result = []
  for i in xrange(10):
    msg = "hello %d" %(i)
    result.append(pool.apply_async(func, (msg, )))
  pool.close()
  pool.join()
  for res in result:
  #res 是一个res 对象, 通过get 拿到返回结果
    print res.get()
  print "Sub-process(es) done."
